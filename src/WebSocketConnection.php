<?php

namespace Wpjscc\Websocket;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\Stream\DuplexStreamInterface;
use React\EventLoop\Loop;

class WebSocketConnection implements EventEmitterInterface
{
    use EventEmitterTrait;

    private $stream;

    /** @var WebSocketOptions */
    private $webSocketOptions;

    /** @var PermessageDeflateOptions */
    private $permessageDeflateOptions;

    private $messageBuffer;

    public $activeTime = 0;

    public $client_id;

    public function __construct(DuplexStreamInterface $stream, WebSocketOptions $webSocketOptions, PermessageDeflateOptions $permessageDeflateOptions)
    {
        $this->stream                   = $stream;
        $this->webSocketOptions         = $webSocketOptions;
        $this->permessageDeflateOptions = $permessageDeflateOptions;
        $this->client_id = bin2hex(openssl_random_pseudo_bytes(16));

        $mb = new MessageBuffer(
            new CloseFrameChecker(),
            function (Message $message) {
                $this->activeTime = time();
                $this->emit('message', [$message, $this]);
            },
            function (Frame $frame) {
                switch ($frame->getOpcode()) {
                    case Frame::OP_PING:
                        $this->activeTime = time();
                        $this->stream->write((new Frame($frame->getPayload(), true, Frame::OP_PONG))->getContents());
                        return;
                    case Frame::OP_PONG:
                        $this->activeTime = time();
                        $this->emit('pong', [$frame, $this]);
                        break;
                    case Frame::OP_CLOSE:
                        $closeCode = unpack('n*', substr($frame->getPayload(), 0, 2));
                        $closeCode = reset($closeCode) ?: 1000;
                        $reason = '';

                        if ($frame->getPayloadLength() > 2) {
                            $reason = substr($frame->getPayload(), 2);
                        }

                        $this->stream->end($frame->getContents());

                        $this->emit('close', [$closeCode, $this, $reason]);

                        $this->send(new Frame(pack('n', $frame->getOpcode()), true, Frame::OP_CLOSE));
                        return;
                }
            },
            true,
            null,
            $this->webSocketOptions->getMaxMessagePayloadSize(),
            $this->webSocketOptions->getMaxFramePayloadSize(),
            [$this->stream, 'write'],
            $this->permessageDeflateOptions
        );

        $this->messageBuffer = $mb;

        $stream->on('data', [$mb, 'onData']);


        $timer = Loop::addPeriodicTimer(30, function()  {
            if (time()-$this->activeTime > 30) {
                $this->close(1000, 'over 30 close ');
            }
        });

        $stream->on('close', function() use (&$timer) {
            Loop::cancelTimer($timer);
            $timer = null;
            $this->emit('close', [0, $this, '']);
        });


    }

    public function send($data)
    {
        if ($data instanceof Frame) {
            $this->messageBuffer->sendFrame($data);
            return;
        }

        if ($data instanceof MessageInterface) {
            $this->messageBuffer->sendMessage($data->getPayload(), true, $data->isBinary());
            return;
        }

        $this->messageBuffer->sendMessage($data);
    }

    public function close($code = 1000, $reason = '')
    {
        $this->stream->end((new Frame(pack('n', $code) . $reason, true, Frame::OP_CLOSE))->getContents());
    }
}
