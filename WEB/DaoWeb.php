<?php
/**
 * Created by PhpStorm.
 * User: sierramws
 * Date: 2020-07-01
 * Time: 10:39
 * Version: 1.1
 */

class DaoWeb{
//messages
    private $data = 'data';
    private $message = 'success';
    private $succeeded = 'success';
    private $failed = 'failed';
    private $debug_msg = 'message';
    protected $conn;

    function  __construct($conn){
        $this->conn = $conn;
    }

    function isEmpty($result) {
        if ($result[$this->message] === $this->failed || empty($result[$this->data])) {
            return true;
        }
        return false;
    }

    function isSuccess($result) {
        return $result[$this->message] === $this->succeeded;
    }

    function success() {
        $data[$this->message] = $this->succeeded;
        $data[$this->data] = array();
        $data[$this->debug_msg] = $this->succeeded;
        return $data;
    }

    function fail($msg) {
        $data[$this->message] = $this->failed;
        $data[$this->data] = array();
        $data[$this->debug_msg] = 'failed at: ' . $msg;
        return $data;
    }

    function getData($sql) {
        $result = $this->conn->query($sql);

        if (!isset($result->num_rows)) {
            return $this->fail($sql);
        }

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $data[$this->data][] = $row;
            }
            $data[$this->message] = $this->succeeded;
        } else {
            $data[$this->data] = array();
            $data[$this->debug_msg] = 'no such result';
            $data[$this->message] = $this->succeeded;
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
            return $this->fail('no data');
        }

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $data[$this->data][] = $row;
            }
            $data[$this->message] = $this->succeeded;
        } else {
            $data[$this->data] = array();
            $data[$this->debug_msg] = 'no such result';
            $data[$this->message] = $this->succeeded;
        }

        return $data;
    }

    function insertData($sql) {
        //insert
        if ($this->conn->query($sql) === TRUE) {
            return $this->success();
        } else {
            return $this->fail($sql);
        }
    }

    /**
     * @deprecated replaced with insert($table_name, $data_map)
     */
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
            $result = $this->fail('safe insert');
        }

        $stmt->close();
        $this->conn->close();

        return $result;
    }

    function select($table_name, $select_row_name, $condition_sql) {
        $sql = "SELECT ";
        if ($select_row_name == null) {
            $sql .= "* ";
        } else {
            $isFirst = true;
            foreach ($select_row_name as $item) {
                if ($isFirst) {
                    $isFirst = false;
                } else {
                    $sql .= ", ";
                }
                $sql .= $item;
            }
        }
        $sql .= " FROM {$table_name}";
        if ($condition_sql == null) {
            return $this->getData($sql);
        } else {
            $sql .= " WHERE {$condition_sql}";
            return $this->getData($sql);
        }
    }

    /*
     * 'selected', 'conTb' ['lblTb'=> 'nTb', 'on' => 0, 1, joSt], condi, orTb, lblOrTb, orBy
     */
    function joinSelect($data_map) {
        $sql = "SELECT ";
        if ($data_map['selected'] == null) {
            $sql .= "* ";
        } else {
            $isFirst = true;
            foreach ($data_map['selected'] as $item) {
                if ($isFirst) {
                    $isFirst = false;
                } else {
                    $sql .= ", ";
                }
                $sql .= $item;
            }
        }

        $sql .= " FROM {$data_map['orTb']} {$data_map['lblOrTb']}";

        foreach($data_map['conTb'] as $key=>$value) {
            $sql .= " {$value['joSt']} {$value['nTb']} {$key}";
            $on = $value['on'];
            $sql .= " ON {$on[0]} = {$on[1]}";
        }

        if ($data_map['condi'] != null) {
            $sql .= " WHERE {$data_map['condi']}";
        }

        if ($data_map['orBy'] != null) {
            $sql .= " ORDER BY {$data_map['orBy']}";
        }

        return $this->getData($sql);
    }

    function delete($table_name, $condition_sql) {
        if ($condition_sql == null) {
            $sql = 'DELETE FROM `'.$table_name.'` WHERE 1';
            return $this->insertData($sql);
        }
        $sql = 'DELETE FROM `'.$table_name.'` WHERE ' . $condition_sql;
        return $this->insertData($sql);
    }

    function update($table_name, $data_map, $condition_sql) {
        $upperSql = "UPDATE `{$table_name}` SET ";
        $lowerSql = ' WHERE ' . $condition_sql;
        $isFirst = true;
        foreach ($data_map as $name=>$value) {
            if ($isFirst) {
                $isFirst = false;
            } else {
                $upperSql.= ', ';
            }
            $upperSql .= "`{$name}` = '{$value}'";
        }
        $sql = $upperSql . $lowerSql;
        return $this->insertData($sql);
    }

    function insert($table_name, $data_map) {
        $upperSql = "INSERT INTO `".$table_name."`(";
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
        return $this->insertData($sql);
    }
}