<?php

namespace CLIENT;

require 'vendor/autoload.php';
require_once 'db.php';

use MyDB\DB as DB;

class API
{

    private $db;
    private $auth;

    public function __construct()
    {
        $this->db = new DB();
        $this->auth = new \Delight\Auth\Auth($this->db->dbh);
    }

    public function log_in($email, $password)
    {
        try {
            $this->auth->login($email, $password, ((empty($_ENV["login_remember_duration"]) || intval($_ENV["login_remember_duration"]) === 0)  ? NULL : $_ENV["login_remember_duration"]));

            return true;
        } catch (\Delight\Auth\InvalidEmailException $e) {
            throw new \Exception('Wrong email address');
        } catch (\Delight\Auth\InvalidPasswordException $e) {
            throw new \Exception('Wrong password');
        } catch (\Delight\Auth\EmailNotVerifiedException $e) {
            throw new \Exception('Email not verified');
        } catch (\Delight\Auth\TooManyRequestsException $e) {
            throw new \Exception('Too many requests');
        }
        return false;
    }

    public function isLoggedIn()
    {
        return $this->auth->isLoggedIn();
    }

    public function isRegisterEnabled()
    {
        return $_ENV["register_enabled"] === "true" ? true : false;
    }

    public function logOut()
    {
        return $this->auth->logOut();
    }

    private function get_user_id()
    {
        return $this->auth->getUserId();
    }

    public function register($email, $password, $username)
    {
        try {
            if (\preg_match('/[\x00-\x1f\x7f\/:\\\\]/', $username) === 0 && $_ENV["register_enabled"] === "true") {
                $userId = $this->auth->registerWithUniqueUsername($email, $password, $username);

                return 'We have signed up a new user with the ID ' . $userId;
            } else {
                throw new \Exception("Unable to register!", 1);
            }
        } catch (\Delight\Auth\InvalidEmailException $e) {
            throw new \Exception("Invalid email address!", 1);
        } catch (\Delight\Auth\InvalidPasswordException $e) {
            throw new \Exception("Invalid password!", 1);
        } catch (\Delight\Auth\UserAlreadyExistsException $e) {
            throw new \Exception("User already exists!", 1);
        } catch (\Delight\Auth\TooManyRequestsException $e) {
            throw new \Exception("Too many requests!", 1);
        } catch (\Delight\Auth\DuplicateUsernameException $e) {
            throw new \Exception("User exists!", 1);
        }
    }

    public function get_wfo_days($year, $month = NULL)
    {
        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") FROM wfo_days WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $days_found = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $days_found;
    }

    public function get_wfo_days_count($year, $month = NULL)
    {
        $query = 'SELECT count(*) FROM wfo_days WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $target_found = $stmt->fetchColumn();
        return $target_found;
    }

    public function get_wfo_days_feed($start, $end)
    {
        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") FROM wfo_days WHERE user_id = :user_id AND defined_date >= :start AND defined_date <= :end';
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, \PDO::PARAM_STR);
        $stmt->bindValue(':end', $end, \PDO::PARAM_STR);
        $stmt->execute();
        $days_found = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") FROM wfo_holidays WHERE user_id = :user_id AND defined_date >= :start AND defined_date <= :end';
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, \PDO::PARAM_STR);
        $stmt->bindValue(':end', $end, \PDO::PARAM_STR);
        $stmt->execute();
        $holidays_found = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") FROM wfo_sickleave WHERE user_id = :user_id AND defined_date >= :start AND defined_date <= :end';
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, \PDO::PARAM_STR);
        $stmt->bindValue(':end', $end, \PDO::PARAM_STR);
        $stmt->execute();
        $sickleave_found = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") FROM wfo_bank_holidays WHERE user_id = :user_id AND defined_date >= :start AND defined_date <= :end';
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, \PDO::PARAM_STR);
        $stmt->bindValue(':end', $end, \PDO::PARAM_STR);
        $stmt->execute();
        $bank_holidays_found = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") as defined_date, overtime_hours FROM wfo_overtime WHERE user_id = :user_id AND defined_date >= :start AND defined_date <= :end';
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':start', $start, \PDO::PARAM_STR);
        $stmt->bindValue(':end', $end, \PDO::PARAM_STR);
        $stmt->execute();
        $overtime_found = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $overtime_days = array_column($overtime_found, 'defined_date');


        $res = [];
        $begin = new \DateTime($start);
        $finish = new \DateTime($end);
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($begin, $interval, $finish);

        foreach ($period as $dt) {
            if (in_array($dt->format("Y-m-d"), $bank_holidays_found)) {
                $res[] = [
                    "title" => "🛋️ Bank holiday",
                    "start" => $dt->format("Y-m-d"),
                    "end" => $dt->format("Y-m-d"),
                    "color" => "#84003e",
                    "cursor" => "pointer"
                ];
            } elseif (in_array($dt->format("Y-m-d"), $holidays_found)) {
                $res[] = [
                    "title" => $_ENV['holiday_label'],
                    "start" => $dt->format("Y-m-d"),
                    "end" => $dt->format("Y-m-d"),
                    "color" => $_ENV['holiday_color'],
                    "cursor" => "pointer"
                ];
            } elseif (in_array($dt->format("Y-m-d"), $sickleave_found)) {
                $res[] = [
                    "title" => $_ENV['sickleave_label'],
                    "start" => $dt->format("Y-m-d"),
                    "end" => $dt->format("Y-m-d"),
                    "color" => $_ENV['sickleave_color'],
                    "cursor" => "pointer"
                ];
            } elseif (in_array($dt->format("Y-m-d"), $days_found)) {
                $res[] = [
                    "title" => $_ENV['office_day_label'],
                    "start" => $dt->format("Y-m-d"),
                    "end" => $dt->format("Y-m-d"),
                    "color" => $_ENV['office_day_color'],
                    "cursor" => "pointer",
                    "id" => 1
                ];
                $res[] = $this->generate_holiday_event($dt);
                $res[] = $this->generate_bank_holiday_event($dt);
                $res[] = $this->generate_sickleave_event($dt);
                $res[] = $this->generate_overtime_event($dt);
                $res[] = $this->generate_book_seat_event($dt);
                $res[] = $this->generate_book_parking_spot_event($dt);
            } else {
                if (in_array($dt->format("N"), [1, 2, 3, 4, 5])) {
                    $res[] = [
                        "title" => $_ENV['home_day_label'],
                        "start" => $dt->format("Y-m-d"),
                        "end" => $dt->format("Y-m-d"),
                        "color" => $_ENV['home_day_color'],
                        "cursor" => "pointer",
                        "id" => 1
                    ];
                    $res[] = $this->generate_holiday_event($dt);
                    $res[] = $this->generate_bank_holiday_event($dt);
                    $res[] = $this->generate_sickleave_event($dt);
                    $res[] = $this->generate_overtime_event($dt);
                    $res[] = $this->generate_book_seat_event($dt);
                    $res[] = $this->generate_book_parking_spot_event($dt);
                }
            }
            $overtime_key = array_search($dt->format("Y-m-d"), $overtime_days);
            if ($overtime_key !== false) {
                $res[] = [
                    "title" => "💪 " . $overtime_found[$overtime_key]['overtime_hours'] . "h Overtime",
                    "start" => $dt->format("Y-m-d"),
                    "end" => $dt->format("Y-m-d"),
                    "color" => "#a832a8",
                    "cursor" => "pointer"
                ];
            }
        }
        return $res;
    }

    private function generate_holiday_event($dt)
    {
        return [
            "title" => "🏖️ Add holiday",
            "start" => $dt->format("Y-m-d"),
            "end" => $dt->format("Y-m-d"),
            "color" => $_ENV['add_holiday_color'],
            "textColor" => $_ENV['add_holiday_text_color'],
            "cursor" => "pointer",
            "id" => 9
        ];
    }

    private function generate_overtime_event($dt)
    {
        return [
            "title" => "💪 Add Overtime",
            "start" => $dt->format("Y-m-d"),
            "end" => $dt->format("Y-m-d"),
            "color" => $_ENV['add_holiday_color'],
            "textColor" => $_ENV['add_holiday_text_color'],
            "cursor" => "pointer",
            "id" => 10
        ];
    }

    private function generate_sickleave_event($dt)
    {
        return [
            "title" => "🤒 Add Sick Leave",
            "start" => $dt->format("Y-m-d"),
            "end" => $dt->format("Y-m-d"),
            "color" => $_ENV['add_holiday_color'],
            "textColor" => $_ENV['add_holiday_text_color'],
            "cursor" => "pointer",
            "id" => 9
        ];
    }

    private function generate_book_seat_event($dt)
    {
        $b = $this->get_booked_seats($dt->format("Y-m-d"));
        if ($b && $b > 0) {
            return [
                "title" => "💺 Booked seat: " . $b['name'],
                "start" => $dt->format("Y-m-d"),
                "end" => $dt->format("Y-m-d"),
                "color" => "#c5005c",
                "cursor" => "pointer",
                "id" => 12
            ];
        }
        return [
            "title" => "💺 Book Seat",
            "start" => $dt->format("Y-m-d"),
            "end" => $dt->format("Y-m-d"),
            "color" => $_ENV['add_holiday_color'],
            "textColor" => $_ENV['add_holiday_text_color'],
            "cursor" => "pointer",
            "id" => 11
        ];
    }

    private function generate_book_parking_spot_event($dt)
    {
        $b = $this->get_booked_parking_spot($dt->format("Y-m-d"));
        if ($b && $b > 0) {
            return [
                "title" => "🚗 Parking Spot: " . $b['name'],
                "start" => $dt->format("Y-m-d"),
                "end" => $dt->format("Y-m-d"),
                "color" => "#82cdff",
                "cursor" => "pointer",
                "id" => 12
            ];
        }
        return [
            "title" => "🚗 Book Parking Spot",
            "start" => $dt->format("Y-m-d"),
            "end" => $dt->format("Y-m-d"),
            "color" => $_ENV['add_holiday_color'],
            "textColor" => $_ENV['add_holiday_text_color'],
            "cursor" => "pointer",
            "id" => 11
        ];
    }

    private function generate_bank_holiday_event($dt)
    {
        return [
            "title" => "🏢 Add Bank holiday",
            "start" => $dt->format("Y-m-d"),
            "end" => $dt->format("Y-m-d"),
            "color" => $_ENV['add_holiday_color'],
            "textColor" => $_ENV['add_holiday_text_color'],
            "cursor" => "pointer",
            "id" => 9
        ];
    }

    public function add_wfo_day($year, $month, $day)
    {
        $query = "REPLACE INTO wfo_days (defined_date, user_id) VALUES (:parsed, :user_id)";

        $stmt = $this->db->dbh->prepare($query);
        $parsed = join("-", [$year, $month, $day]);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':parsed', $parsed, \PDO::PARAM_STR);

        $result = $stmt->execute();

        return $result;
    }

    public function delete_wfo_day($day)
    {
        $query = "DELETE from wfo_days WHERE user_id = :user_id AND defined_date = :day";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':day', $day, \PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function switch_wfo_day($day)
    {
        $query = 'SELECT count(*) as count FROM wfo_days WHERE user_id = :user_id AND defined_date = :day';
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':day', $day, \PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->execute();
        $days_found = $stmt->fetchAll(\PDO::FETCH_COLUMN)[0];

        if ($days_found > 0) {
            $result = $this->delete_wfo_day($day);
        } else {
            $result = $this->delete_wfo_holidays($day);
            $result = $this->delete_wfo_sickleave($day);
            $result = $this->delete_wfo_bank_holidays($day);

            $query = "REPLACE INTO wfo_days (defined_date, user_id) VALUES (:day, :user_id)";

            $stmt = $this->db->dbh->prepare($query);
            $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
            $stmt->bindValue(':day', $day, \PDO::PARAM_STR);

            $result = $stmt->execute();
        }

        return $result;
    }

    public function get_wfo_month_target($year, $month)
    {
        $query = "select target from wfo_month_target WHERE month_of_target = :month_of_target AND year_of_target = :year_of_target AND user_id = :user_id limit 1";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':month_of_target', $month, \PDO::PARAM_INT);
        $stmt->bindValue(':year_of_target', $year, \PDO::PARAM_INT);

        $stmt->execute();
        $target_found = $stmt->fetchColumn();
        return $target_found;
    }

    public function add_wfo_month_target($year, $month, $target)
    {
        $query = "REPLACE INTO wfo_month_target (month_of_target, year_of_target, `target`, user_id) VALUES (:month_of_target, :year_of_target, :target, :user_id)";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':month_of_target', $month, \PDO::PARAM_INT);
        $stmt->bindValue(':year_of_target', $year, \PDO::PARAM_INT);
        $stmt->bindValue(':target', $target, \PDO::PARAM_INT);

        $result = $stmt->execute();

        return $result;
    }

    public function get_wfo_year_target($year)
    {
        $query = "select target from wfo_year_target WHERE year_of_target = :year_of_target AND user_id = :user_id limit 1";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year_of_target', $year, \PDO::PARAM_INT);

        $stmt->execute();
        $target_found = $stmt->fetchColumn();
        return $target_found;
    }

    public function add_wfo_year_target($year, $target)
    {
        $query = "REPLACE INTO wfo_year_target (year_of_target, `target`, user_id) VALUES (:year_of_target, :target, :user_id)";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year_of_target', $year, \PDO::PARAM_INT);
        $stmt->bindValue(':target', $target, \PDO::PARAM_INT);

        $result = $stmt->execute();

        return $result;
    }

    public function get_wfo_working_days($year, $month = NULL)
    {
        $query = "select working_days ";
        if (is_null($month)) {
            $query = "select SUM(working_days) ";
        }
        $query .= " from wfo_working_days WHERE `year` = :year AND user_id = :user_id ";
        if (!is_null($month)) {
            $query .= " and `month` = :month ";
        }
        $query .= " limit 1";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();
        $target_found = $stmt->fetchColumn();
        return $target_found;
    }


    public function add_wfo_working_days($year, $month, $working_days)
    {
        $query = "REPLACE INTO wfo_working_days (`year`, `month`, working_days, user_id) VALUES (:year, :month, :working_days, :user_id)";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        $stmt->bindValue(':working_days', $working_days, \PDO::PARAM_INT);

        $result = $stmt->execute();

        return $result;
    }

    public function get_wfo_holidays($year, $month = NULL)
    {
        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") FROM wfo_holidays WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $days_found = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $days_found;
    }

    public function get_wfo_holidays_count($year, $month = NULL)
    {
        $query = 'SELECT count(*) FROM wfo_holidays WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $target_found = $stmt->fetchColumn();
        return $target_found;
    }

    public function add_wfo_holiday($day)
    {
        $this->delete_wfo_day($day);

        $query = "REPLACE INTO wfo_holidays (defined_date, user_id) VALUES (:day, :user_id)";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':day', $day, \PDO::PARAM_STR);

        $result = $stmt->execute();

        return $result;
    }

    public function delete_wfo_holidays($day)
    {
        $query = "DELETE from wfo_holidays WHERE user_id = :user_id AND defined_date = :day";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':day', $day, \PDO::PARAM_STR);
        return $stmt->execute();
    }


    public function get_wfo_bank_holidays($year, $month = NULL)
    {
        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") FROM wfo_bank_holidays WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $days_found = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $days_found;
    }

    public function add_wfo_bank_holidays($day)
    {
        $this->delete_wfo_day($day);

        $query = "REPLACE INTO wfo_bank_holidays (defined_date, user_id) VALUES (:day, :user_id)";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':day', $day, \PDO::PARAM_STR);

        $result = $stmt->execute();

        return $result;
    }

    public function delete_wfo_bank_holidays($day)
    {
        $query = "DELETE from wfo_bank_holidays WHERE user_id = :user_id AND defined_date = :day";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':day', $day, \PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function get_wfo_sickleave($year, $month = NULL)
    {
        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") FROM wfo_sickleave WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $days_found = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $days_found;
    }

    public function get_wfo_sickleave_count($year, $month = NULL)
    {
        $query = 'SELECT count(*) FROM wfo_sickleave WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $target_found = $stmt->fetchColumn();
        return $target_found;
    }

    public function add_wfo_sickleave($day)
    {
        $this->delete_wfo_day($day);

        $query = "REPLACE INTO wfo_sickleave (defined_date, user_id) VALUES (:day, :user_id)";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':day', $day, \PDO::PARAM_STR);

        $result = $stmt->execute();

        return $result;
    }

    public function delete_wfo_sickleave($day)
    {
        $query = "DELETE from wfo_sickleave WHERE user_id = :user_id AND defined_date = :day";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':day', $day, \PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function add_wfo_overtime($date, $hours)
    {
        $query = "REPLACE INTO wfo_overtime (defined_date, overtime_hours, user_id) VALUES (:defined_date, :overtime_hours, :user_id)";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':defined_date', $date, \PDO::PARAM_STR);
        $stmt->bindValue(':overtime_hours', $hours, \PDO::PARAM_STR);

        $result = $stmt->execute();

        return $result;
    }

    public function delete_wfo_overtime($date)
    {
        $query = "DELETE from wfo_overtime WHERE user_id = :user_id AND defined_date = :date";

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':date', $date, \PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function get_wfo_overtime($year, $month = NULL)
    {
        $query = 'SELECT DATE_FORMAT(defined_date , "%Y-%m-%d") as defined_date, overtime_hours FROM wfo_overtime WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $days_found = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $days_found;
    }

    public function get_wfo_overtime_hours_sum($year, $month = NULL)
    {
        $query = 'SELECT SUM(overtime_hours) FROM wfo_overtime WHERE user_id = :user_id AND YEAR(defined_date) = :year ';
        if (!is_null($month)) {
            $query .= " and MONTH(defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $sum = $stmt->fetchColumn();
        return $sum ? $sum : 0;
    }

    public function get_wfo_overtime_hours_sum_office_only($year, $month = NULL)
    {
        $query = 'SELECT SUM(t1.overtime_hours) FROM wfo_overtime as t1 INNER JOIN wfo_days as t2 ON t1.defined_date = t2.defined_date AND t1.user_id = t2.user_id WHERE t1.user_id = :user_id AND YEAR(t1.defined_date) = :year';
        if (!is_null($month)) {
            $query .= " and MONTH(t1.defined_date) = :month";
        }

        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        if (!is_null($month)) {
            $stmt->bindValue(':month', $month, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $sum = $stmt->fetchColumn();
        return $sum ? $sum : 0;
    }

    public function generate_wfo_custom_command()
    {
        $prepared_commands = [];
        $query = 'SELECT command, days_in_advance FROM wfo_custom_command_generator WHERE user_id = :user_id ';
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->execute();
        $commands = $stmt->fetchAll();

        foreach ($commands as $command) {
            $query = 'SELECT DATE_FORMAT(DATE_SUB(defined_date, INTERVAL :days_in_advance DAY), "%d.%m.%Y") as "date" FROM wfo_days WHERE user_id = :user_id and defined_date > DATE_ADD(CURRENT_DATE, INTERVAL :days_in_advance DAY)';
            $stmt = $this->db->dbh->prepare($query);
            $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
            $stmt->bindValue(':days_in_advance', $command['days_in_advance'], \PDO::PARAM_INT);
            $stmt->execute();
            $days_for_placeholder = $stmt->fetchAll();

            foreach ($days_for_placeholder as $days) {
                $prepared_commands[] = str_replace("[placeholder]", $days['date'], $command['command']);
            }
        }

        return $prepared_commands;
    }

    public function generate_access_token($tokenName)
    {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        $token = $selector . ':' . $validator;
        $hashedValidator = hash('sha256', $validator);

        $query = "REPLACE INTO wfo_api_tokens (selector, hashed_validator, token_name, user_id) VALUES (:selector, :hashed_validator, :token_name, :user_id)";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':selector', $selector, \PDO::PARAM_STR);
        $stmt->bindValue(':hashed_validator', $hashedValidator, \PDO::PARAM_STR);
        $stmt->bindValue(':token_name', $tokenName, \PDO::PARAM_STR);
        $result = $stmt->execute();

        if ($result) {
            return $token;
        } else {
            throw new \Exception("Unable to generate token!", 1);
        }
    }

    public function get_access_tokens()
    {
        $query = "SELECT id, token_name, selector FROM wfo_api_tokens WHERE user_id = :user_id";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->execute();
        $tokens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $tokens;
    }

    public function revoke_access_token($token_id)
    {
        $query = "DELETE FROM wfo_api_tokens WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':id', $token_id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function get_info($token, $in_x_days)
    {
        $parts = explode(':', $token);
        if (count($parts) !== 2) {
            return ["status" => "home", "date" => null];
        }
        $selector = $parts[0];
        $validator = $parts[1];

        $query = 'SELECT t2.hashed_validator, t2.user_id FROM wfo_api_tokens as t2 WHERE t2.selector = :selector LIMIT 1';
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':selector', $selector, \PDO::PARAM_STR);
        $stmt->execute();
        $token_data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($token_data && hash_equals($token_data['hashed_validator'], hash('sha256', $validator))) {
            $query = 'SELECT t1.defined_date FROM wfo_days as t1 WHERE t1.user_id = :user_id AND t1.defined_date = DATE_ADD(CURRENT_DATE, INTERVAL :in_x_days DAY) LIMIT 1';
            $stmt = $this->db->dbh->prepare($query);
            $stmt->bindValue(':user_id', $token_data['user_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':in_x_days', $in_x_days, \PDO::PARAM_INT);
            $stmt->execute();
            $day_found = $stmt->fetchColumn();

            try {
                $parking_spot = $this->get_booked_parking_spot($day_found, $token_data['user_id']);
            } catch (\Throwable $th) {
                $parking_spot = null;
            }

            try {
                $seat = $this->get_booked_seats($day_found, $token_data['user_id']);
            } catch (\Throwable $th) {
                $seat = null;
            }

            if ($day_found) {
                return [
                    "status" => "office",
                    "date" => $day_found,
                    "parking_spot" => $parking_spot ? $parking_spot['name'] : "No parking spot booked",
                    "seat" => $seat ? $seat['name'] : "No seat booked"
                ];
            }
        }

        return [
            "status" => "home",
            "date" => null
        ];
    }

    public function get_settings()
    {
        $query = "SELECT days_to_show, language FROM settings WHERE user_id = :user_id";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $r = [];
        $r['days_to_show'] = $result['days_to_show'] ? explode(",", $result['days_to_show']) : [];
        $r['language'] = $result['language'] ?? 'en';
        return $r;
    }

    public function save_settings($days_to_show, $language)
    {
        if (is_array($days_to_show)) {
            $days_to_show = implode(",", $days_to_show);
        } else {
            $days_to_show = "";
        }
        $query = "REPLACE INTO settings (days_to_show, language, user_id) VALUES (:days_to_show, :language, :user_id)";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':days_to_show', $days_to_show, \PDO::PARAM_STR);
        $stmt->bindValue(':language', $language, \PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $result = $stmt->execute();

        if ($result) {
            return true;
        } else {
            throw new \Exception("Unable to save settings!", 1);
        }
    }

    public function map_allowed_types()
    {
        return ['office', 'parking'];
    }

    public function save_map($map, $name, $imageBoundsX, $imageBoundsY, $type)
    {
        $query = "INSERT INTO maps (map, user_id, name, type, imageBoundsY, imageBoundsX) VALUES (:map, :user_id, :name, :type, :imageBoundsY, :imageBoundsX)";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':map', $map, \PDO::PARAM_LOB);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, \PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, \PDO::PARAM_STR);
        $stmt->bindValue(':imageBoundsY', $imageBoundsY, \PDO::PARAM_STR);
        $stmt->bindValue(':imageBoundsX', $imageBoundsX, \PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function delete_map($map_id)
    {
        $query = "DELETE FROM maps WHERE id = :map_id AND user_id = :user_id";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':map_id', $map_id, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function get_maps($info_only = false)
    {
        if ($info_only) {
            $query = "SELECT id, name, type FROM maps WHERE user_id = :user_id";
        } else {
            $query = "SELECT id, map, name, type FROM maps WHERE user_id = :user_id";
        }
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function get_map($id)
    {
        $query = "SELECT id, map, name, type, imageBoundsX, imageBoundsY FROM maps WHERE user_id = :user_id AND id = :id";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result) {
            $result['map'] = "data:image/png;base64," . $result['map'];
            return $result;
        }
        throw new \Exception("Map not found!", 1);
    }

    public function get_map_seats($map_id)
    {
        $query = "SELECT s1.* FROM seats s1 JOIN maps s2 ON s1.map_id = s2.id WHERE s1.map_id = :map_id AND s2.user_id = :user_id";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':map_id', $map_id, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function get_map_seat($seat_id)
    {
        $query = "SELECT * FROM seats s1 JOIN maps s2 ON s1.map_id = s2.id WHERE s1.seat_id = :seat_id AND s2.user_id = :user_id";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':seat_id', $seat_id, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function get_map_seat_by_name($seat_name)
    {
        $query = "SELECT s1.id, s1.map_id FROM seats s1 JOIN maps s2 ON s1.map_id = s2.id WHERE s1.name = :seat_name AND s2.user_id = :user_id";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':seat_name', $seat_name, \PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function create_seat($map_id, $name, $description, $bookable, $x, $y)
    {
        $user_map = $this->get_map($map_id);
        if (!$user_map) {
            throw new \Exception("Map not found!", 1);
        }
        $query = "INSERT INTO seats (map_id, name, description, bookable, x_coordinate, y_coordinate) VALUES (:map_id, :name, :description, :bookable, :x, :y)";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':map_id', $map_id, \PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, \PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, \PDO::PARAM_STR);
        $stmt->bindValue(':bookable', $bookable, \PDO::PARAM_INT);
        $stmt->bindValue(':x', $x, \PDO::PARAM_STR);
        $stmt->bindValue(':y', $y, \PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function bulk_create_seats($map_id, $json_data)
    {
        try {
            $user_map = $this->get_map($map_id);
            if (!$user_map) {
                throw new \Exception("Map not found!", 1);
            }

            $q1 = "DELETE FROM seats WHERE map_id = :map_id";
            $stmt1 = $this->db->dbh->prepare($q1);
            $stmt1->bindValue(':map_id', $map_id, \PDO::PARAM_INT);
            $stmt1->execute();

            $query = "INSERT INTO seats (map_id, name, description, bookable, x_coordinate, y_coordinate) VALUES (:map_id, :name, :description, :bookable, :x, :y)";
            $stmt = $this->db->dbh->prepare($query);

            foreach ($json_data as $seat) {
                $stmt->bindValue(':map_id', $map_id, \PDO::PARAM_INT);
                $stmt->bindValue(':name', $seat['name'], \PDO::PARAM_STR);
                $stmt->bindValue(':description', $seat['description'], \PDO::PARAM_STR);
                $stmt->bindValue(':bookable', $seat['bookable'], \PDO::PARAM_INT);
                $stmt->bindValue(':x', $seat['x_coordinate'], \PDO::PARAM_STR);
                $stmt->bindValue(':y', $seat['y_coordinate'], \PDO::PARAM_STR);
                $stmt->execute();
            }
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Unable to bulk create seats!", 1);
        }
    }

    public function book_seat($seat_id, $reservation_date)
    {
        try {
            $user_map_seat = $this->get_map_seat($seat_id);
            if (!$user_map_seat) {
                throw new \Exception("Seat not found!", 1);
            }

            $query = "INSERT INTO user_seats (seat_id, user_id, reservation_date) VALUES (:seat_id, :user_id, :reservation_date)";
            $stmt = $this->db->dbh->prepare($query);
            $stmt->bindValue(':seat_id', $seat_id, \PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
            $stmt->bindValue(':reservation_date', $reservation_date, \PDO::PARAM_STR);
            return $stmt->execute();
        } catch (\Exception $e) {
            throw new \Exception("Unable to book seat!", 1);
        }
    }

    public function book_seat_by_name($seat_name, $reservation_date, $map_id)
    {
        try {
            if (empty($seat_name)) {
                $query_delete = "DELETE FROM user_seats WHERE user_id = :user_id AND reservation_date = :reservation_date AND map_id = :map_id";
                $stmt_delete = $this->db->dbh->prepare($query_delete);
                $stmt_delete->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
                $stmt_delete->bindValue(':reservation_date', $reservation_date, \PDO::PARAM_STR);
                $stmt_delete->bindValue(':map_id', $map_id, \PDO::PARAM_INT);
                $stmt_delete->execute();
                return true;
            } else {
                $user_map_seat = $this->get_map_seat_by_name($seat_name);
                if (!$user_map_seat) {
                    throw new \Exception("Seat not found!", 1);
                }
                if ($user_map_seat['map_id'] != $map_id) {
                    throw new \Exception("Seat does not belong to the specified map!", 1);
                }

                $query_delete = "DELETE FROM user_seats WHERE user_id = :user_id AND reservation_date = :reservation_date AND map_id = :map_id";
                $stmt_delete = $this->db->dbh->prepare($query_delete);
                $stmt_delete->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
                $stmt_delete->bindValue(':reservation_date', $reservation_date, \PDO::PARAM_STR);
                $stmt_delete->bindValue(':map_id', $map_id, \PDO::PARAM_INT);
                $stmt_delete->execute();

                $query = "INSERT INTO user_seats (seat_id, user_id, reservation_date, map_id) VALUES (:seat_id, :user_id, :reservation_date, :map_id)";
                $stmt = $this->db->dbh->prepare($query);
                $stmt->bindValue(':seat_id', $user_map_seat['id'], \PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
                $stmt->bindValue(':reservation_date', $reservation_date, \PDO::PARAM_STR);
                $stmt->bindValue(':map_id', $map_id, \PDO::PARAM_INT);
                return $stmt->execute();
            }
        } catch (\Exception $e) {
            throw new \Exception("Unable to book seat!", 1);
        }
    }

    public function unbook_seat($seat_id, $reservation_date)
    {
        try {
            $user_map_seat = $this->get_map_seat($seat_id);
            if (!$user_map_seat) {
                throw new \Exception("Seat not found!", 1);
            }

            $query = "DELETE FROM user_seats WHERE seat_id = :seat_id AND user_id = :user_id AND reservation_date = :reservation_date";
            $stmt = $this->db->dbh->prepare($query);
            $stmt->bindValue(':seat_id', $seat_id, \PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
            $stmt->bindValue(':reservation_date', $reservation_date, \PDO::PARAM_STR);
            return $stmt->execute();
        } catch (\Exception $e) {
            throw new \Exception("Unable to unbook seat!", 1);
        }
    }

    public function get_booked_seats($reservation_date, $user_id = null)
    {
        $query = "SELECT s1.*, us1.user_id FROM user_seats us1 JOIN seats s1 ON us1.seat_id = s1.id JOIN maps m1 ON s1.map_id = m1.id WHERE us1.reservation_date = :reservation_date AND m1.user_id = :user_id AND m1.type = 'office'";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':reservation_date', $reservation_date, \PDO::PARAM_STR);
        $stmt->bindValue(':user_id', is_null($user_id) ? $this->get_user_id() : $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function get_booked_parking_spot($reservation_date, $user_id = null)
    {
        $query = "SELECT s1.*, us1.user_id FROM user_seats us1 JOIN seats s1 ON us1.seat_id = s1.id JOIN maps m1 ON s1.map_id = m1.id WHERE us1.reservation_date = :reservation_date AND m1.user_id = :user_id AND m1.type = 'parking'";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':reservation_date', $reservation_date, \PDO::PARAM_STR);
        $stmt->bindValue(':user_id', is_null($user_id) ? $this->get_user_id() : $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }


    public function get_recent_seats($map_type = null)
    {
        $query = "SELECT DISTINCT s1.name FROM seats s1 JOIN user_seats us1 ON s1.id = us1.seat_id JOIN maps m1 ON s1.map_id = m1.id WHERE us1.user_id = :user_id ";
        if (!is_null($map_type)) {
            $query .= " AND m1.type = :map_type ";
        }
        $query .= " limit 10";
        $stmt = $this->db->dbh->prepare($query);
        $stmt->bindValue(':user_id', $this->get_user_id(), \PDO::PARAM_INT);
        if (!is_null($map_type)) {
            $stmt->bindValue(':map_type', $map_type, \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
