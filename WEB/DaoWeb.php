<?php
/**
 * Created by PhpStorm.
 * User: sierramws
 * Date: 2020-07-01
 * Time: 10:39
 * Version: 1.0
 */

class DaoWeb{
//messages
    private $message = 'success';
    private $success = 'success';
    private $failed = 'failed';
    protected $conn;

    function  __construct($conn){
        $this->conn = $conn;
    }

    function isEmpty($result) {
        if ($result[$this->message] === $this->failed || empty($result['data'])) {
            return true;
        }
        return false;
    }

    function success() {
        $data[$this->message] = $this->success;
        $data['data'] = array();
        $data['message'] = 'success';
        return $data;
    }

    function failed($msg) {
        $data[$this->message] = $this->failed;
        $data['data'] = array();
        $data['message'] = 'failed at: ' . $msg;
        return $data;
    }

    function getData($sql) {
        $result = $this->conn->query($sql);

        if (!isset($result->num_rows)) {
            return $this->failed($sql);
        }

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $data['data'][] = $row;
            }
            $data[$this->message] = $this->success;
        } else {
            $data['data'] = array();
            $data['message'] = 'no such result';
            $data[$this->message] = $this->success;
        }

        return $data;
    }

    function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    function safeGet($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        if (!isset($result->num_rows)) {
            return $this->failed('no data');
        }

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $data['data'][] = $row;
            }
            $data[$this->message] = $this->success;
        } else {
            $data['data'] = array();
            $data['message'] = 'no such result';
            $data[$this->message] = $this->success;
        }

        return $data;
    }

    function insertData($sql) {
        //insert
        if ($this->conn->query($sql) === TRUE) {
            return $this->success();
        } else {
            return $this->failed($sql);
        }
    }

    function insertSQLFromMap($tableName, $data_map) {
        $upperSql = "INSERT INTO `".$tableName."`(";
        $lowerSql = ") VALUES (";
        $count = 0;
        foreach ($data_map as $name => $value) {
            if ($count == 0) {
                $count++;
            } else {
                $upperSql .= ", ";
                $lowerSql .= ", ";
            }
            $upperSql .= "`{$name}`";
            $lowerSql .= "'{$value}'";
        }
        $sql = $upperSql . $lowerSql . ")";
        return $sql;
    }

    function safeInsert($stmt) {
        if ($stmt->execute() === TRUE) {
            $result = $this->success();
        } else {
            $result = $this->failed('safe insert');
        }

        $stmt->close();
        $this->conn->close();

        return $result;
    }
}