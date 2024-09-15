<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Session;

use DI\DependencyException;
use DI\NotFoundException;
use GeoIp2\Exception\AddressNotFoundException;
use Modules\Session\Handler\SessionHandler;

class SessionManager extends SessionHandler {

    use SessionTrait;

    /**
     * @var array|string[]
     */
    private static array $status=[
        0=>'Session is desabled.',
        1=>'Session is inactive.',
        2=>'Session is active.'
    ];

    /**
     * @var string
     */
    protected string $data;

    /**
     * @var string
     */
    protected static string $sessionId = '';

    /**
     * @var array|int[]
     * @see https://php.net/session.configuration for options
     * but we omit 'session.' from the beginning of the keys for convenience.
     *
     * ("auto_start", is not supported as it tells PHP to start a session before
     * PHP starts to execute user-land code. Setting during runtime has no effect).
     *
     * cache_limiter, "" (use "0" to prevent headers from being sent entirely).
     * cache_expire, "0"
     * cookie_domain, ""
     * cookie_httponly, ""
     * cookie_lifetime, "0"
     * cookie_path, "/"
     * cookie_secure, ""
     * cookie_samesite, null
     * gc_divisor, "100"
     * gc_maxlifetime, "1440"
     * gc_probability, "1"
     * lazy_write, "1"
     * name, "PHPSESSID"
     * referer_check, ""
     * serialize_handler, "php"
     * use_strict_mode, "0"
     * use_cookies, "1"
     * use_only_cookies, "1"
     * use_trans_sid, "0"
     * upload_progress.enabled, "1"
     * upload_progress.cleanup, "1"
     * upload_progress.prefix, "upload_progress_"
     * upload_progress.name, "PHP_SESSION_UPLOAD_PROGRESS"
     * upload_progress.freq, "1%"
     * upload_progress.min-freq, "1"
     * url_rewriter.tags, "a=href,area=href,frame=src,form=,fieldset="
     * sid_length, "32"
     * sid_bits_per_character, "5"
     * trans_sid_hosts, $_SERVER['HTTP_HOST']
     * trans_sid_tags, "a=href,area=href,frame=src,form="
     */
    protected array $options=[
        "gc_maxlifetime" => 86400,
        "gc_probability" => 100,
        "cookie_httponly" => true,
        "cookie_secure" => true, // true -> wenn https
        "use_only_cookies" => true,
        "use_strict_mode" => true,
        "use_trans_sid" => false
    ];

    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws AddressNotFoundException
     */
    public function registry(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start($this->options);
        }

        $session=$this->getSessionEntity()::find(self::getSessionId());
        if (is_null($session)){
            $data = serialize($_SESSION);
            if ($data) {
                $this->write(self::getSessionId(), $data);
            }
        }
        else {
            $_SESSION=unserialize(base64_decode($session->data));
        }

        if ($session->user_id === 0 && filter_input(INPUT_SERVER, "REMOTE_ADDR")!=="172.17.0.1") {
            $statsEntity = $this->getStatisticManager()->getStatsEntity();
            $server = $_SERVER;
            $ipAddress = $server['REMOTE_ADDR'];

            $stats = new $statsEntity();
            $stats->session_id=self::getSessionId();
            $stats->ip=$ipAddress;
            $stats->os=$this->getStatisticModel()->getPlatform($server);
            $stats->platform=$this->getStatisticModel()->getReferer($server);
            $stats->country=$this->getStatisticModel()->getCountry($server);
            $stats->city=$this->getStatisticModel()->getCity($server);
            $stats->referer=$server["REQUEST_URI"];
            $stats->save();
        }
    }

    /**
     * @return array
     */
    public static function getStatus(): array {
        $code=session_status();
        return [
            'code'=>$code,
            'message'=>self::$status[$code]
        ];
    }

    /**
     * @return false|string|null
     */
    public static function getSessionId(): bool|string|null {
        if (!empty(self::$sessionId)){
            return self::$sessionId;
        }
        else {
            self::$sessionId=session_id();
            if (!empty(self::$sessionId)){
                return self::$sessionId;
            }
            else {
                return null;
            }
        }
    }

    /**
     * @return mixed|null
     */
    public function getUserId(): mixed {
        if ($this->has('user_id')){
            return $this->get('user_id');
        }
        return null;
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function setUserId(int $user_id): void {
        $this->set('user_id', $user_id);
    }

    /**
     * @return bool
     */
    public function hasUserId(): bool {
        return $this->has('user_id');
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function set(string $name, mixed $value): void {
        $_SESSION[$name]=$value;
        $this->commit();
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function get(string $name): mixed {
        if (array_key_exists($name, $_SESSION)){
            return $_SESSION[$name];
        }
        else {
            return null;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool {
        return array_key_exists($name, $_SESSION);
    }

    /**
     * @param string $name
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function remove(string $name): void {
        unset($_SESSION[$name]);
        $this->commit();
    }

    /**
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function commit(): void {
        $data = serialize($_SESSION);
        if (session_status() === 2) {
            $this->write($this->getSessionId(), $data);
        }
    }

    /**
     * @return bool
     */
    public function close(): bool {
        return session_destroy();
    }

    /**
     * @param string $id
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function destroy(string $id): bool {
        $this->getSessionEntity()::where('id', '=', $id)->delete();
        return true;
    }

    /**
     * @param int $max_lifetime
     * @return false|int
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function gc(int $max_lifetime): false|int {
        $limit = time() - ($max_lifetime * 3);
        $this->getSessionEntity()::where('timestamp', '<', $limit)->delete();
        return $max_lifetime;
    }

    /**
     * @param string $path
     * @param string $name
     * @return bool
     */
    public function open(string $path, string $name): bool {
        return session_start($this->options);
    }

    /**
     * @param string $id
     * @return false|string
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function read(string $id): false|string {
        $session=$this->getSessionEntity()::find($id);
        if (is_null($session)){
            $sessionEntity=$this->getSessionEntity();
            $session=new $sessionEntity();
            $session->id=self::getSessionId();
            $session->data=base64_encode(serialize($_SESSION));
            $session->timestamp=time();
            $session->save();
        }
        $_SESSION=unserialize(base64_decode($session->data));
        return $_SESSION;
    }

    /**
     * @param string $id
     * @param string|null $data
     * @return bool
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function write(string $id, string|null $data): bool {
        $session=$this->getSessionEntity()::find($id);
        if (is_null($session)) {
            $sessionEntity=$this->getSessionEntity();
            $session = new $sessionEntity();
            $session->id=self::getSessionId();
        }
        if ($this->has('user_id')){
            $session->user_id = $this->get('user_id');
        }
        $session->data=base64_encode($data);
        $session->timestamp=time();
        return $session->save();
    }
}
