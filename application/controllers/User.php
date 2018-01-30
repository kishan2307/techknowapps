<?php
defined('BASEPATH') or exit('No direct script access allowed');

class User extends CI_Controller
{
    public function login()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->UserModel->check_auth_client();
            $body = getRequestBodyArray($this->input->raw_input_stream);
            if ($check_auth_client == true) {                
                if (isset($body->email)) {
                    if (isset($body)) {
                        $response = $this->UserModel->loginV2($body);
                        json_output($response['status'], $response);
                    }
                } else {
                    json_output(400, array('status' => 400, 'message' => 'required parameter missing.', 'params' => $_POST));
                }
            }
        }
    }

    public function addPoint()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $body = getRequestBodyArray($this->input->raw_input_stream);
            if (isset($body)) {
                $userid = base64_decode($body->uid);
                $token = $body->token;
                if ($this->UserModel->validateToken($userid, $token)) {
                    $this->Operation->addPoints($body->tag, $userid);
                }
            }
        }
    }

    public function getHistory()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $body = getRequestBodyArray($this->input->raw_input_stream);
            if (isset($body)) {
                $userid = $body->uid;
                $token = $body->token;
                if ($this->UserModel->validateToken($userid, $token)) {
                    $this->db->cache_on();
                    $data = $this->db->select('tag_name', 'tag_id', 'credit', 'date')->from('credit_history')->where('user_id', $userid)->order_by('date', 'desc')->limit(10)->get()->result();
                    $this->db->cache_off();
                    json_output(200, array('status' => 200, 'message' => 'sucess.', 'data' => $data));
                }
            }
        }
    }

    public function addGiftRedeemRequest()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $body = getRequestBodyArray($this->input->raw_input_stream);
            if (isset($body)) {
                $userid = $body->uid;
                $token = $body->token;
                if ($this->UserModel->validateToken($userid, $token)) {
                    $this->Operation->addRedeemRequest($userid, $body);
                }
            }
        }
    }

    public function addSupportRequest()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $token = $body->token;
            $body = getRequestBodyArray($this->input->raw_input_stream);
            if (isset($body)) {
                $userid = $body->uid;
                if ($this->UserModel->validateToken($userid, $token)) {
                    $this->Operation->addSupportRequest($userid, $body);
                }
            }
        }
    }

    public function getGiftCards()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $this->db->cache_on();
            $result = $this->db->get('gift_cards')->result();
            $this->db->cache_off();
            json_output(200, array('status' => 200, 'message' => 'sucess.', 'data' => $result));
        }
    }


    public function getMoreApps()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $this->db->cache_on();
            $result = $this->db->get('more_apps')->result();
            $this->db->cache_off();
            json_output(200, array('status' => 200, 'message' => 'sucess.', 'data' => $result));
        }
    }


    public function getGiftHistory()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $body = getRequestBodyArray($this->input->raw_input_stream);
            if (isset($body)) {
                $userid = $body->uid;
                $token = $body->token;
                if ($this->UserModel->validateToken($userid, $token)) {
                    $data = $this->db->from('redeem_request')->where('user_id', $userid)->order_by('date', 'desc')->limit(10)->get()->result();
                    json_output(200, array('status' => 200, 'message' => 'sucess.', 'data' => $data));
                }
            }
        }
    }

    function addParentPoint()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $body = getRequestBodyArray($this->input->raw_input_stream);
            if (isset($body)) {
                $userid = $body->uid;
                $token = $body->token;
                if ($this->UserModel->validateToken($userid, $token)) {
                    $parentcode = $body->parent_code;
                    if (isset($parentcode)) {
                        $this->Operation->addParentPoints($userid, $parentcode);
                    }
                }
            }
        }
    }

    function getFriendsList()
    {
        $check_auth_client = $this->UserModel->check_auth_client();
        if ($check_auth_client == true) {
            $body = getRequestBodyArray($this->input->raw_input_stream);
            if (isset($body)) {
                $userid = $body->uid;
                $token = $body->token;
                if ($this->UserModel->validateToken($userid, $token)) {
                    $this->db->select('user_details.username,user_details.email');
                    $data = $this->db->query('SELECT `username`,`email` FROM `user_details` WHERE id IN(SELECT friends_id FROM user_friends WHERE user_id=' . $userid . ')')->result();
                    if (isset($data) && count($data) > 0) {
                        json_output(200, array('status' => 200, 'message' => 'sucess.', 'data' => $data));
                    } else {
                        json_output(200, array('status' => 1003, 'message' => 'you don\'t have any friends.'));
                    }
                }
            }
        }
    }
}
