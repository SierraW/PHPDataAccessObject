<?php
/**
 * Created by PhpStorm.
 * User: sierramws
 * Date: 2020-07-01
 * Time: 10:39
 * Version: 2.0
 */

class DaoWeb{
//messages
    private $data = 'data';
    private $message = 'success';
    private $succeeded = 'success';
    private $failed = 'failed';
    private $debug_msg = 'message';
    private $inserted_id = 'inserted_id';
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
            $result = $this->success();
            $result[$this->inserted_id] = $this->conn->insert_id;
            return $result;
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
            $result[$this->inserted_id] = $this->conn->insert_id;
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
    $data_map = [
                'selected' => ['a.id', 'a.user_session', 'a.display_name', 'b.title_name', 'c.role_name'],
                'lblOrTb' => 'a',
                'orTb' => $this->primary_table_name,
                'conTb' => [
                    'b' => [
                        'joSt' => 'LEFT JOIN',
                        'nTb' => $this->title_table_name,
                        'on' => ['a.title_id', 'b.title_id']
                    ],
                    'c' => [
                        'joSt' => 'LEFT JOIN',
                        'nTb' => $this->role_table_name,
                        'on' => ['a.role_id', 'c.role_id']
                    ]
                ],
                'condi' => "user_login = ?",
                'orBy' => null,
                'bindStr' => 's',
                'bindParams' => [$output['data']['user_login']]
            ];
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

        $sql .= " FROM `{$data_map['orTb']}`";

        if ($data_map['lblOrTb'] != null) {
            $sql .= " {$data_map['lblOrTb']}";
        }

        if ($data_map['conTb'] != null) {
            foreach($data_map['conTb'] as $key=>$value) {
                $sql .= " {$value['joSt']} `{$value['nTb']}` {$key}";
                $on = $value['on'];
                $sql .= " ON {$on[0]} = {$on[1]}";
            }
        }

        if ($data_map['condi'] != null) {
            $sql .= " WHERE {$data_map['condi']}";
        }

        if ($data_map['orBy'] != null) {
            $sql .= " ORDER BY {$data_map['orBy']}";
        }

        if ($data_map['bindStr'] == null) {
            return $this->getData($sql);
        }

        $stmt = $this->prepare($sql);

        call_user_func_array(array($stmt, "bind_param"), array_merge(array($data_map['bindStr']), $this->mkReferenceArr($data_map['bindParams'])));

        return $this->safeGet($stmt);

    }

    private function mkReferenceArr($arr) {
        $getRef = function ($element) {
            $ref = &$element;
            return $ref;
        };
        return array_map($getRef, $arr );
    }

    function delete($table_name, $condition_sql) {
        if ($condition_sql == null) {
            $sql = 'DELETE FROM `'.$table_name.'` WHERE 1';
            return $this->insertData($sql);
        }
        $sql = 'DELETE FROM `'.$table_name.'` WHERE ' . $condition_sql;
        return $this->insertData($sql);
    }

    /*
     * version 1.0
        $data_map = [
            'user_session' => $session,
            'session_expire_time' => $this->sessionExpiryTime()
        ];
     * version 2.0
        $data_map = [
            "version" => 2,
            "tn",
            "vn" => ["room_type", "signature"],
            "val" => ["1", $signature],
            "condi" => "",
            "bp" => [0,1],
            "bs" => "ss"
        ];
     *
     */

    function update($data_map, $data_map_deprecated=null, $condition_sql_deprecated=null) {
        if ($data_map_deprecated != null && $condition_sql_deprecated != null) {
            $upperSql = "UPDATE `{$data_map}` SET ";
            $lowerSql = ' WHERE ' . $condition_sql_deprecated;

            $isFirst = true;
            foreach ($data_map_deprecated as $name=> $value) {
                if ($isFirst) {
                    $isFirst = false;
                } else {
                    $upperSql.= ', ';
                }
                $upperSql .= "`{$name}` = '{$value}'";
            }
            $sql = $upperSql . $lowerSql;
            return $this->insertData($sql);
        } else if ($data_map['version'] === 2) {
            $bindMode = false;
            if ($data_map['bp'] && $data_map['bs']) {
                $bindMode = true;
            }

            $upperSql = "UPDATE `{$data_map['tn']}` SET ";
            $lowerSql = $data_map['condi'] ? ' WHERE ' . $data_map['condi'] : '';
            $isFirst = true;
            $bindParams = [];
            for ($i = 0 ; $i < count($data_map['vn']); $i++) {
                if ($isFirst) {
                    $isFirst = false;
                } else {
                    $upperSql.= ', ';
                }
                $upperSql .= "`{$data_map['vn'][$i]}` = ";
                if ($bindMode && in_array($i, $data_map['bp'])) {
                    $upperSql .= "?";
                    array_push($bindParams, $data_map['val'][$i]);
                } else {
                    $upperSql .= "'{$data_map['val'][$i]}'";
                }
            }
            $sql = $upperSql . $lowerSql;

            if (!$bindMode) {
                return $this->insertData($sql);
            }

            $stmt = $this->prepare($sql);

            call_user_func_array(array($stmt, "bind_param"), array_merge(array($data_map['bs']), $this->mkReferenceArr($bindParams)));

            return $this->safeInsert($stmt);

        }


    }

    /*
         $data_map = [
           "version" => 1,
          "room_type" => 1,
          "signature" => $signature
        ];
    $data_map = [
        "version" => 2,
        "tn" => $table_name,
        "vn" => ["room_type", "signature"],
        "val" => [
            ["1", $signature],
            ["2", $signature2]
        ],
        "bp" => [0,1],
        "bs" => "ss"
    ];

     */
    function insert($data_map, $data_map_deprecated=null) {
        if ($data_map_deprecated != null && !$data_map_deprecated['version'] && $data_map_deprecated['version'] < 2) {
            $upperSql = "INSERT INTO `".$data_map."`(";
            $lowerSql = ") VALUES (";
            $count = 0;
            foreach ($data_map_deprecated as $name => $value) {
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
        } else if ( $data_map['version'] == 2) {
            $bindMode = false;
            if ($data_map['bp'] && $data_map['bs']) {
                $bindMode = true;
            }
            $upperSql = "INSERT INTO `".$data_map['tn']."` ";
            $lowerSql = " VALUES ";
            $count = 0;
            $upperSql .= "(";
            foreach ($data_map['vn'] as $var) {
                if ($count == 0) {
                    $count++;
                } else {
                    $upperSql .= ", ";
                }
                $upperSql .= "`{$var}`";
            }
            $upperSql .= ")";

            for ($j = 0; $j < count($data_map['val']); $j++) {
                $value = $data_map['val'][$j];
                if ($j != 0) {
                    $lowerSql .= ", ";
                }
                $lowerSql .= "(";
                for ($i = 0; $i < count($value); $i++) {
                    if ($i != 0) {
                        $lowerSql .= ", ";
                    }
                    if ($bindMode && in_array($i, $data_map['bp'])) {
                        $lowerSql .= "?";
                    } else {
                        $lowerSql .= "'{$value[$i]}'";
                    }
                }
                $lowerSql .= ")";
            }

            $sql = $upperSql . $lowerSql;

            if ($bindMode) {
                $bindStr = '';
                $bindParams = [];

                for($i = 0; $i < count($data_map['val']); $i++) {
                    $bindStr .= $data_map['bs'];
                    foreach ( $data_map['bp'] as $p) {
                        array_push($bindParams, $data_map['val'][$i][$p]);
                    }
                }

                $stmt = $this->prepare($sql);

                call_user_func_array(array($stmt, "bind_param"), array_merge(array($bindStr), $this->mkReferenceArr($bindParams)));

                return $this->safeInsert($stmt);

            }

            return $this->insertData($sql);
        }
    }
}