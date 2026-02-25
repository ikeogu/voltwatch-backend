<?php

namespace App\Traits;

use App\Http\Handlers\GuzzleRequestHandler;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleLogMiddleware\LogMiddleware;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

trait GuzzleLogger
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function createHandlerStack(): HandlerStack
    {
        $logger = (new Logger('guzzle'))
            ->pushHandler(new RotatingFileHandler(
                storage_path('logs/guzzle.log')
            ));

       // $logChannel = app()->get('log')->channel('stack');

        $stack = HandlerStack::create(new CurlHandler());
       // $stack->push(new LogMiddleware($logger, new GuzzleRequestHandler()));

        return $stack;
    }
}
