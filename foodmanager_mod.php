<?php

/**
 * foodmanager
 *
 * @package   php-bot
 * @author    Patryk Kordylewski
 * @copyright Copyright (c) 2008
 * @version   $Id$
 * @access    public
 */
class foodmanager {
    public static $food_day_id;
    public static $msgs;

    public static function get_msgs() {
        return array_map('utf8_decode', self::$msgs);
    }

    public static function get_food_data() {
        $q = "SELECT
                fg.label,
                fg.descr,
                fg.url
              FROM
                food_days fd
                INNER JOIN food_groups fg USING (food_group_id)
              WHERE
                fd.day = now()::date;";
        return pg::fetch_assoc($q);
    }

    public static function status() {
        $q = "SELECT
                fd.food_day_id,
                fd.food_group_id,
                fd.close_stamp
              FROM
                food_days fd
              WHERE
                fd.day = now()::date;";
        if (($food_day = pg::fetch_assoc($q))) {
            // Abgeschlossen
            if (strlen($food_day['close_stamp'])) {
                return 'finished';
            // Warte auf Bestellungen
            } else {
                self::$food_day_id = $food_day['food_day_id'];
                return 'accepting';
            }
        } else {
            // Heute noch nichts passiert
            return 'none';
        }
    }

    public static function info() {
        self::$msgs = array();
        $q = "SELECT
                (
                  SELECT
                    COUNT(*)
                  FROM
                    foods f
                  WHERE
                    f.food_day_id = fd.food_day_id AND
                    f.request IS NOT NULL
                ) AS user_count,
                array_to_string(ARRAY(
                  SELECT
                    u.username
                  FROM
                    foods f
                    INNER JOIN users u USING (user_id)
                  WHERE
                    u.deleted IS FALSE AND
                    f.food_day_id = fd.food_day_id AND
                    f.request IS NOT NULL
                ), ', ') AS users,
                array_to_string(ARRAY(
                  SELECT
                    u.username
                  FROM
                    users u
                    LEFT JOIN foods f ON (
                      u.user_id     = f.user_id AND
                      f.food_day_id = fd.food_day_id
                    )
                  WHERE
                    u.deleted IS FALSE AND
                    f.user_id IS NULL
                ), ', ') AS missing_users,
                fd.close_stamp IS NOT NULL AS completed,
                fg.label AS food_group,
                COALESCE(fg.descr, 'Keine') AS food_group_descr,
                COALESCE(fg.url, 'Keine') AS food_group_url,
                (
                  SELECT
                    u.username
                  FROM
                    users u
                  WHERE
                    u.user_id = fd.create_user_id
                ) AS create_user
              FROM
                food_days fd
                INNER JOIN food_groups fg USING (food_group_id)
              WHERE
                fd.day = now()::date;";
        if (($info = pg::fetch_assoc($q))) {
            if ($info['completed'] == 't') {
                self::$msgs[] = " - ACHTUNG: Es werden KEINE Bestellungen mehr aufgenommen!";
            }

            if (isset($info['create_user']) && strlen($info['create_user'])) {
                self::$msgs[] = " - Organisator/Fahrer: {$info['create_user']}";
            }
            self::$msgs[] = " - Heute gibts: {$info['food_group']} Zusatzinfo: {$info['food_group_descr']} URL: {$info['food_group_url']}";
            self::$msgs[] = " - {$info['user_count']} Bestellung(en) wurden aufgenommen von: {$info['users']}";
            self::$msgs[] = " - Es fehlen Bestellungen von: {$info['missing_users']}";
            self::$msgs[] = " - Informationen per Web: http://bot.fooby.de/index.php?action=fm_orders";

        } else {
            self::$msgs = array(
                ' - Es wurde für den heutigen Tag noch kein Bestellprozess initialisiert.',
            );
        }
        return true;
    }

    public static function foods() {
        self::$msgs = array("Verfügbare Futterliste:");

        $q = "SELECT
                fg.food_group_id,
                fg.label,
                fg.descr,
                fg.url
              FROM
                food_groups fg
              ORDER BY
                fg.food_group_id;";
        if ($food_groups = pg::fetch_all($q)) {
            foreach ($food_groups as $food_group) {
              self::$msgs[] = "#{$food_group['food_group_id']}: {$food_group['label']} {$food_group['descr']} {$food_group['url']}";
            }
        }

        return true;
    }

    public static function users() {
        self::$msgs = array("Verfügbare Benutzerliste:");

        $q = "SELECT
                u.username,
                u.email
              FROM
                users u
              WHERE
                u.deleted IS FALSE
              ORDER BY
                u.username;";
        if ($users = pg::fetch_all($q)) {
            foreach ($users as $user) {
              self::$msgs[] = "{$user['username']} {$user['email']}";
            }
        }

        return true;
    }

    public static function orders() {
        // Infomeldungen generieren
        self::info();

        $q = "SELECT
                u.username,
                f.request
              FROM
                food_days fd
                INNER JOIN foods f USING (food_day_id)
                INNER JOIN users u USING (user_id)
              WHERE
                fd.day = now()::date AND
                f.request IS NOT NULL
              ORDER BY
                f.request;";
        if ($foods = pg::fetch_all($q)) {
            foreach ($foods as $food) {
              self::$msgs[] = "{$food['username']} : {$food['request']}";
            }
        }

        return true;
    }
    
    public static function ping() {
        $q = "SELECT
                u.username
              FROM
                food_days fd
                INNER JOIN foods f USING (food_day_id)
                INNER JOIN users u USING (user_id)
              WHERE
                fd.day = now()::date AND
                f.request IS NOT NULL
              ORDER BY
                u.username;";
        if ($data = pg::fetch_all($q)) {
            foreach ($data as $user) {
              $users[] = $user['username'];
            }
            self::$msgs = array(implode(' ', $users));
        }

        return true;
    }

    public static function start($food_group_id, $username) {
        if (self::status() != 'none') {
            self::$msgs = array(
                "Fehler: Es wurde bereits ein Bestellprozess initialisiert.",
            );
            return false;
        }

        if (!($create_user_id = self::find_user_id($username))) {
            self::$msgs = array(
                "Fehler: Ich konnte deinen IRC-Nicknamen nicht finden. Alle gültigen Benutzer findest du unter dem Befehl: !fm users",
            );
            return false;
        }

        $pg_data = array(
            'day'               => pg::fetch_result("SELECT now()::date;"),
            'food_group_id'     => $food_group_id,
            'create_user_id'    => $create_user_id,
        );
        if (pg::insert('food_days', $pg_data)) {
            self::$msgs = array(
                "Bestellprozess erfolgreich initialisiert.",
            );
            return true;
        } else {
            self::$msgs = array(
                "Fehler: Start des Bestellprozesses fehlgeschlagen.",
            );
            return false;
        }
    }

    public static function set($food_group_id) {
        if (self::status() != 'accepting') {
            self::$msgs = array(
                "Fehler: Kein offener Bestellprozess verfügbar.",
            );
            return false;
        }

        $pg_data = array(
            'day'           => pg::fetch_result("SELECT now()::date;"),
            'food_group_id' => $food_group_id,
        );
        if (pg::update('food_days', $pg_data, "day = '" . pg_escape_string($pg_data['day']) . "' AND close_stamp IS NULL")) {
            self::$msgs = array(
                "Die Futtergruppe wurde erfolgreich gesetzt.",
            );
            return true;
        } else {
            self::$msgs = array(
                "Fehler: Die Futtergruppe konnte nicht gesetzt werden.",
            );
            return false;
        }
    }

    public static function find_user_id($username) {
        $username = strtolower($username);
        $username = trim($username, '__');
        $username = trim($username, '_');

        $q = "SELECT
                u.user_id
              FROM
                users u
              WHERE
                u.deleted IS FALSE AND
                LOWER(u.username) = '" . pg_escape_string($username) . "';";
        return pg::fetch_result($q);
    }

    public static function close() {
        if (self::status() != 'accepting') {
            self::$msgs = array(
                "Fehler: Kein offener Bestellprozess verfügbar der geschlossen werden könnte.",
            );
            return false;
        }

        $pg_data = array(
            'day'           => pg::fetch_result("SELECT now()::date;"),
            'close_stamp'   => pg::fetch_result("SELECT now();"),
        );

        if (pg::update('food_days', $pg_data, "day = '" . pg_escape_string($pg_data['day']) . "' AND close_stamp IS NULL")) {
            self::$msgs = array(
                "Der Bestellprozess wurde abgeschlossen, keine weiteren Bestellungen möglich.",
            );
            return true;
        } else {
            self::$msgs = array(
                "Fehler: Bestellprozess konnte nicht abgeschlossen werden.",
            );
            return false;
        }
    }

    public static function open() {
        if (self::status() != 'finished') {
            self::$msgs = array(
                "Fehler: Bestellvorgang ist nicht abgeschlossen und kann somit nicht wieder geöffnet werden.",
            );
            return false;
        }

        $pg_data = array(
            'close_stamp' => '',
        );

        if (pg::update('food_days', $pg_data, "day = now()::date AND close_stamp IS NOT NULL")) {
            self::$msgs = array(
                "Der Bestellprozess wurde wieder geöffnet, Bestellungen wieder möglich.",
            );
            return true;
        } else {
            self::$msgs = array(
                "Fehler: Bestellprozess konnte nicht geöffnet werden.",
            );
            return false;
        }
    }

    public static function nix($username) {
        if (($return = self::add($username, ''))) {
            self::$msgs = array(
                "Du wurdest erfolgreich aus dem Bestellprozess ausgenommen. Solltest du deine Meinung ändern kannst du über '!fm add' trotzdem noch eine Bestellung aufgeben."
            );
        }

        return $return;
    }

    public static function add($username, $request) {
        if (self::status() != 'accepting') {
            self::$msgs = array(
                "Fehler: Es können keine Bestellungen entgegen genommen werden.",
            );
            return false;
        }

        if (!($user_id = self::find_user_id($username))) {
            self::$msgs = array(
                "Fehler: Ich konnte deinen IRC-Nicknamen nicht finden. Alle gültigen Benutzer findest du unter dem Befehl: !fm users",
            );
            return false;
        }

        $pg_data = array(
            'user_id'     => $user_id,
            'food_day_id' => self::$food_day_id,
            'request'     => trim($request),
        );

        if (pg::upsert('foods', $pg_data, "user_id = '" . pg_escape_string($pg_data['user_id']) . "' AND food_day_id = '" . self::$food_day_id . "'")) {
            self::$msgs = array(
                "Die Bestellung wurde erfolgreich aufgenommen, sie lautet: '{$request}'.",
            );
            return true;
        } else {
            self::$msgs = array(
                "Fehler: Die Bestellung konnte nicht entgegengenommen werden.",
            );
            return false;
        }
    }

    public static function help() {
        self::$msgs = array(
            "Allgemeine Funktionen:",
            " !fm info - Allgemeine Informationen was es zu essen gibt und wer schon was bestellt hat. Für detaillierte Informationen '!fm orders' verwenden.",
            " !fm notify - Versendet Info E-Mails an alle Benutzer.",
            " !fm add <text> - Setzt eine Bestellung ab, z.B. !fm add 1x Metzger Tagesessen und ein LKW",
            " !fm nix - Markiert dich als Benutzer der heute nichts essen bzw. bestellen mag, kann durch ein nachträgliches '!fm add' wieder aufgehoben werden.",
            " !fm next - Ermittelt den Benutzer der als nächstes Essen holen gehen sollte.",
            "Informationsfunktionen:",
            " !fm orders - Gibt im Query alle Bestellungen aller Personen aus.",
            " !fm foods - Gibt eine Futterliste zurück mit einigen Informationen unter anderem der ID für !fm set|start <food_group_id>",
            " !fm users - Gibt alle Benutzer im System zurück. Nur diese Nicknamen werden erkannt und müssen dem Nicknamen im IRC entsprechen.",
            "Administrative Funktionen:",
            " !fm start <food_group_id> - Startet den heutigen Bestellprozess mit einer Futtergruppe, z.B. Metzger, Thai, Hoagies, etc. Siehe !fm foods für <food_group_id>.",
            " !fm set <food_group_id> - Verändert die Futtergruppe.",
            " !fm close - Schliesst den Bestellprozess ab. Danach werden keine Bestellungen mehr akzeptiert.",
            " !fm open - Öffnet einen abgeschlossen Bestellprozess wieder.",
        );
        return true;
    }

    public static function next() {
        $users = array();
        $q = "SELECT
                r.user_id,
                r.username,
                r.driver_count,
                r.eater_count,
                r.driver_count::NUMERIC / r.eater_count::NUMERIC AS driver_ratio
              FROM
                (
                  SELECT
                    u.user_id,
                    u.username,
                    (
                      SELECT
                        COUNT(*)
                      FROM
                        food_days fd
                      WHERE
                        fd.create_user_id = u.user_id AND
                        (
                          fd.day < now()::date OR
                          fd.close_stamp IS NOT NULL
                        )
                    ) AS driver_count,
                    (
                      SELECT
                        COUNT(*)
                      FROM
                        food_days fd
                      INNER JOIN foods f ON (
                        fd.food_day_id = f.food_day_id AND
                        f.user_id = u.user_id AND
                        f.request IS NOT NULL
                      )
                      WHERE
                        fd.create_user_id IS NOT NULL AND
                        fd.create_user_id != u.user_id AND
                        (
                          fd.day < now()::date OR
                          fd.close_stamp IS NOT NULL
                        )
                    ) AS eater_count
                  FROM
                    users u
                  WHERE
                    u.deleted IS FALSE
                ) AS r
              WHERE
                r.eater_count > 0
              ORDER BY
                driver_ratio,
                driver_count,
                eater_count DESC;";
        if ($nextUsers = pg::fetch_all($q)) {
            foreach ($nextUsers as $nextUser) {
                $nextUser['driver_ratio'] = number_format($nextUser['driver_ratio'], 4);
                $users[] = "{$nextUser['username']} ({$nextUser['driver_ratio']} = {$nextUser['driver_count']}/{$nextUser['eater_count']})";
            }
        }

        if (isset($users) && count($users)) {
            self::$msgs = array();
            foreach (array_chunk($users, 4) as $chunk) {
                self::$msgs[] = implode(', ', $chunk);
            }
            return true;
        }

        self::$msgs = array(
            "Ooops, der nächste Fahrer konnte nicht ermittelt werden. :-("
        );
        return false;
    }

    public static function notify() {
        self::$msgs = array();
        ;
        $food_info  = '';

        $headers = 'From: foodmanager@fooby.de' . "\r\n" .
                   'Reply-To: pk@fooby.de';

        $subject = "fooby Food-Manager";

        if ($food_group = self::get_food_data()) {
            $subject   .= ", Heute im Programm: {$food_group['label']}";
            $food_info = <<<INFO
Das heutige Menü:
{$food_group['label']}
{$food_group['descr']}
{$food_group['url']}
INFO;
        }

        $body = <<<BODY
Sehr geehrte Damen und Herren,

bitte betreten Sie den IRC Kanal #imos.clutter und geben Sie Ihren heutigen Menüwunsch ab.

{$food_info}

Der dortige Bestelldienst fooby wird mit folgenden Befehlen Ihren Menüwunsch entgegennehmen:
!fm add <Ihr Menüwunsch in Abhängigkeit des aktuellen Programms>

Das aktuelle Menü erfahren Sie durch:
!fm info

Weitere Befehle finden Sie in der Hilfe:
!fm help

Vielen Dank
fooby
BODY;

        $q = "SELECT
                u.username,
                u.email
              FROM
                users u
              WHERE
                u.deleted IS FALSE AND
                u.notify IS TRUE;";
        foreach (($users = pg::fetch_all($q)) as $user) {
            mail($user['email'], $subject, $body, $headers);
        }

        self::$msgs = array(
            "Erfolgreich " . sizeof($users) . " Benutzer per E-Mail informiert.",
        );

        return true;
    }
}

class foodmanager_mod extends module {
    public $title    = "foodmanager module";
    public $author   = "Patryk Kordylewski";
    public $version  = "0.2";

    private $timername = 'foodmanager_mod';
    private $timersecs = 60;

    public function init() {
        $this->timerClass->addTimer($this->timername, $this, "trigger", "", $this->timersecs, false);
    }

    public function destroy() {
        $this->timerClass->removeTimer($this->timername);
    }

    public function trigger() {
        return true;
    }

    public function debug(&$var) {
        ob_start();
        var_dump($var);
        $this->ircClass->privMsg('endless', str_replace("\n", ' ', ob_get_clean()));
    }

    public function handler($line, $args) {
        $query = substr($args['query'], strlen($args['arg1'])+1);
        $query = fm_is_utf8($query) ? $query : utf8_encode($query);

        $channel = $line['to'] == 'fooby' ? $line['fromNick'] : $line['to'];
        $sender  = $line['fromNick'];

        switch ($args['arg1']) {
        case 'foods':
        case 'users':
        case 'orders':
            $callback = array('foodmanager', $args['arg1']);
            if (call_user_func_array($callback, array())) {
                foreach (foodmanager::get_msgs() as $msg){
                    $this->ircClass->privMsg($sender, $msg);
                }
            }
            break;

        case 'info':
            if (foodmanager::info()) {
                foreach (foodmanager::get_msgs() as $msg){
                    $this->ircClass->privMsg($channel, $msg);
                }
            }
            break;

        case 'add':
            foodmanager::add($line['fromNick'], $query);

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($sender, $msg);
            }
            break;

        case 'nix':
            foodmanager::nix($line['fromNick']);

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($sender, $msg);
            }
            break;

        case 'start':
            foodmanager::start($args['arg2'], $line['fromNick']);

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($channel, $msg);
            }
            break;

        case 'set':
            foodmanager::set($args['arg2']);

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($channel, $msg);
            }
            break;

        case 'close':
            foodmanager::close();

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($channel, $msg);
            }
            break;

        case 'open':
            foodmanager::open();

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($channel, $msg);
            }
            break;

        case 'notify':
            foodmanager::notify();

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($sender, $msg);
            }
            break;

        case 'next':
            foodmanager::next();

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($channel, $msg);
            }
            break;
        
        case 'ping':
            foodmanager::ping();

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($channel, $msg);
            }
            break;
        case 'help':
        default:
            foodmanager::help();

            foreach (foodmanager::get_msgs() as $msg){
                $this->ircClass->privMsg($sender, $msg);
            }
            break;
        }
    }
}

function fm_log($string) {
    file_put_contents(__DIR__ . '/debug.log', $string . "\n", FILE_APPEND);
}

function fm_is_utf8($string) {
    return $string === utf8_encode(utf8_decode($string));
}

class pg {
    public static $connected = false;

    public static function connect() {
        if (self::$connected) {
            return true;
        }
        if (pg_connect('host=localhost dbname=imos user=webdev')) {
            self::$connected = true;
            pg_query('SET search_path TO foodmanager, public;');
            return true;
        }
        return false;
    }

    public static function query($q) {
        if (!self::connect()) {
            return false;
        }
        fm_log($q);
        return pg_query($q);
    }

    public static function fetch_assoc($q) {
        if (!self::connect()) {
            return false;
        }
        $qr = self::query($q);
        if (pg_num_rows($qr) == 1) {
            return pg_fetch_assoc($qr);
        }
        return false;
    }

    public static function fetch_result($q) {
        if (!self::connect()) {
            return false;
        }
        $qr = self::query($q);
        if (pg_num_rows($qr) > 0) {
            return pg_fetch_result($qr, 0, 0);
        }
        return false;
    }

    public static function fetch_all($q) {
        if (!self::connect()) {
            return false;
        }
        $qr = self::query($q);
        if (pg_num_rows($qr) > 0) {
            return pg_fetch_all($qr);
        }
        return false;
    }

    public static function insert($table, $fields) {
        if (!self::connect()) {
            return false;
        }

        $values  = array();
        $columns = array();

        foreach ($fields as $column => $value){
            if ($value === '' || $value === null) {
                $values[]  = "NULL";
            } else {
                $values[]  = "'" . pg_escape_string($value) . "'";
            }
            $columns[] = $column;
        }

        // Insert
        $qr = self::query("INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES ( " . implode(', ', $values) . ");");

        return pg_affected_rows($qr) > 0;
    }

    public static function update($table, $fields, $condition) {
        if (!self::connect()) {
            return false;
        }

        $set = array();
        foreach ($fields as $column => $value){
            if ($value === '' || $value === null) {
                $set[] = "{$column} = NULL";
            } else {
                $set[] = "{$column} = '" . pg_escape_string($value) . "'";
            }
        }

        $qr = self::query("UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$condition};");

        return pg_affected_rows($qr) > 0;
    }

    public static function upsert($table, $fields, $condition) {
        if (!self::connect()) {
            return false;
        }

        if (!($return = self::update($table, $fields, $condition))) {
            $return = self::insert($table, $fields);
        }

        return $return;
    }
}

?>