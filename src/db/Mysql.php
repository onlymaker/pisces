<?php

namespace db;

class Mysql extends \Prefab
{
    private $db;
    private $expireTime;

    function get()
    {
        if (time() > $this->expireTime ?? 0) {
            $f3 = \Base::instance();
            $this->db = new SQL(
                $f3->get('MYSQL_DSN'),
                $f3->get('MYSQL_USER'),
                $f3->get('MYSQL_PASSWORD')
            );
            list($timeout) = $this->db->exec('show variables like ?', ['wait_timeout']);
            $this->expireTime = time() + $timeout['Value'] - 60;
        }
        return $this->db;
    }
}
