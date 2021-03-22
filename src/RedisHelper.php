<?php

namespace Brucep\WordPress\RedisHelper;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;

final class RedisHelper
{
    private static ?RedisTagAwareAdapter $adapter;

    public static function getAdapter(): ?RedisTagAwareAdapter
    {
        if (defined('BPWP_REDIS_DISABLED') && BPWP_REDIS_DISABLED) {
            return null;
        }

        if (!isset(self::$adapter)) {
            try {
                self::$adapter = new RedisTagAwareAdapter(
                    RedisAdapter::createConnection(
                        defined('BPWP_REDIS_DSN') ? BPWP_REDIS_DSN : 'redis://localhost:6379',
                        defined('BPWP_REDIS_OPTIONS') ? BPWP_REDIS_OPTIONS : []
                    ),
                    defined('BPWP_REDIS_NAMESPACE') ? BPWP_REDIS_NAMESPACE : '',
                    defined('BPWP_REDIS_LIFETIME') ? BPWP_REDIS_LIFETIME : 0
                );
            } catch (InvalidArgumentException $e) {
                // Redis server refused connection or is not available
                self::$adapter = null;
            }
        }

        return self::$adapter;
    }

    public static function fetch(
        string $key,
        \Closure $closure,
        $tags = [],
        ?int $expiry = null)
    {
        if ($adapter = self::getAdapter()) {
            return $adapter->get(
                $key,
                function (ItemInterface $item) use ($key, $closure, $tags, $expiry) {
                    if (null !== $expiry) {
                        $item->expiresAfter($expiry);
                    }

                    if (!empty($tags)) {
                        $item->tag($tags);
                    }

                    return $closure($key);
                }
            );
        } else {
            return $closure($key);
        }
    }
}
