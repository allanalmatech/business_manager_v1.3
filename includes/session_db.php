<?php
// includes/session_db.php
declare(strict_types=1);


final class DbSessionHandler implements SessionHandlerInterface
{
    /** @var mysqli */
    private $db;

    /** @var int */
    private $ttl;

    public function __construct(mysqli $db, $ttlSeconds = 7200)
    {
        $this->db  = $db;
        $this->ttl = (int)$ttlSeconds;
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $now = time();
        $min = $now - $this->ttl;

        $stmt = $this->db->prepare("SELECT data FROM sessions WHERE id=? AND last_activity >= ? LIMIT 1");
        if (!$stmt) return '';

        $stmt->bind_param("si", $id, $min);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) return '';
        return (string)$row['data'];
    }

    public function write($id, $data)
    {
        $now = time();
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
        $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

        // IMPORTANT: session might not be started yet when write() is first called
        $userId = null;
        if (isset($_SESSION) && isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            $userId = (int)$_SESSION['user']['id'];
        }

        $sql = "
            INSERT INTO sessions (id, user_id, data, ip_address, user_agent, last_activity)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              user_id=VALUES(user_id),
              data=VALUES(data),
              ip_address=VALUES(ip_address),
              user_agent=VALUES(user_agent),
              last_activity=VALUES(last_activity)
        ";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;

        // If userId is null, bind 0 and store NULL using NULLIF in SQL OR set it to NULL via separate query.
        // Easiest: store NULL by converting 0 to NULL in SQL using NULLIF(?,0)
        // We'll do it safely by adjusting SQL when $userId is null.
        if ($userId === null) {
            $sql2 = "
                INSERT INTO sessions (id, user_id, data, ip_address, user_agent, last_activity)
                VALUES (?, NULL, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  user_id=NULL,
                  data=VALUES(data),
                  ip_address=VALUES(ip_address),
                  user_agent=VALUES(user_agent),
                  last_activity=VALUES(last_activity)
            ";
            $stmt->close();
            $stmt = $this->db->prepare($sql2);
            if (!$stmt) return false;

            $stmt->bind_param("ssssi", $id, $data, $ip, $ua, $now);
        } else {
            $stmt->bind_param("sisssi", $id, $userId, $data, $ip, $ua, $now);
        }

        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }

    public function destroy($id)
    {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id=?");
        if (!$stmt) return false;

        $stmt->bind_param("s", $id);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }

    public function gc($max_lifetime)
    {
        $now = time();
        $min = $now - $this->ttl;

        $stmt = $this->db->prepare("DELETE FROM sessions WHERE last_activity < ?");
        if (!$stmt) return 0;

        $stmt->bind_param("i", $min);
        $stmt->execute();
        $count = (int)$stmt->affected_rows;
        $stmt->close();

        return $count;
    }
}
