<?php

namespace Kesara;

use Closure;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\ServerBag;

class CloudflareRealIpServiceProvider extends ServiceProvider
{

    public function boot()
    {

        if (static::isTrustedRequest()) {

            $currentIp = request()->getClientIps();
            $realIp = static::ip();
            $serverParams = request()->server->all();

            request()->server = new ServerBag([
                    'BACKUP_REMOTE_ADDR' => $currentIp, //backup the current ip
                    'REMOTE_ADDR' => $realIp //set the real ip
                ] + $serverParams);


        }

    }


    /**
     * List of IP's used by CloudFlare.
     * @return array
     * @var array
     */
    protected static function ips()
    {
        $ipv4 = file_get_contents("https://www.cloudflare.com/ips-v4");
        $ipv6 = file_get_contents("https://www.cloudflare.com/ips-v6");

        return (array_merge(explode("\n", $ipv4), explode("\n", $ipv6))) ? array_filter(array_merge(explode("\n", $ipv4), explode("\n", $ipv6))) : [];
    }

    /**
     * Checks if current request is coming from CloudFlare servers.
     *
     * @return bool
     */
    public static function isTrustedRequest()
    {
        return IpUtils::checkIp(request()->ip(), self::ips());
    }


    /**
     * Executes a callback on a trusted request.
     *
     * @param Closure $callback
     *
     * @return mixed
     */
    public static function onTrustedRequest(Closure $callback)
    {
        if (static::isTrustedRequest()) {
            return $callback();
        }
    }


    /**
     * Determines "the real" IP address from the current request.
     *
     * @return string
     */
    public static function ip()
    {
        return static::onTrustedRequest(function () {
            return filter_var(request()->header('CF_CONNECTING_IP'), FILTER_VALIDATE_IP);
        }) ?: request()->ip();
    }


    /**
     * Determines country from the current request.
     *
     * @return string
     */
    public static function country()
    {
        return static::onTrustedRequest(function () {
            return request()->header('CF_IPCOUNTRY');
        }) ?: '';
    }

}