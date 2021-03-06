<?php declare(strict_types=1);
namespace LightApp\Model\Service;

class SessionService
{
    private const SESSIONS_PATH = APP_ROOT_DIR . '/tmp/sessions';

    public function __construct()
    {
        if (!file_exists(self::SESSIONS_PATH)) {
            mkdir(self::SESSIONS_PATH, 0775, true);
        }
        ini_set('session.save_path', self::SESSIONS_PATH);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '10');
        ini_set('session.gc_maxlifetime', '60');
        $this->regenerate();
    }

    public function regenerate() : void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        session_regenerate_id(true);
    }

    public function get(array $keys = [], bool $destroy = false) : array
    {
        if (empty($keys)) {
            return $_SESSION;
        }

        $return = [];
        foreach ($keys as $key) {
            if (isset($_SESSION[$key])) {
                $return[$key] = $_SESSION[$key];
            } else {
                $return[$key] = null;
            }
            if ($destroy) {
                $this->set([$key => null]);
            }
        }

        return $return;
    }

    public function set(array $variables) : void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            throw new \Exception('No active session');
        }

        foreach ($variables as $key => $value) {
            if (!is_null($value)) {
                $_SESSION[$key] = $value;
            } else {
                unset($_SESSION[$key]);
            }
        }
    }

    public function destroy() : void
    {
         $_SESSION = [];
         if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', 1);
         }
         session_destroy();
    }
}
