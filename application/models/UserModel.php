<?php
defined('BASEPATH') or exit('No direct script access allowed');

class UserModel extends CI_Model
{

    var $client_service = "app-client";
    var $auth_key = "api";

    public function check_auth_client()
    {
        $client_service = $this->input->get_request_header('Client-Service', true);
        $auth_key = $this->input->get_request_header('Auth-Key', true);
        if ($client_service == $this->client_service && $auth_key == $this->auth_key) {
            return true;
        } else {
            json_output(401, array('status' => 401, 'message' => 'Unauthorized.'));
        }
    }

    public function validateToken($userid, $token)
    {
        if (isset($userid) && isset($token)) {
            $q = $this->db->select('token')->from('users_authentication')->where('user_id', $userid)->get()->row();
            if ($q->token == $token) {
                return true;
            } else {
                json_output(401, array('status' => 401, 'message' => 'invalid token.'));
            }
        } else {
            json_output(401, array('status' => 401, 'message' => 'userid or token empty.'));
        }
    }

    public function login($param)
    {
        $q = $this->db->select('id')->from('user_details')->where('email', $param['email'])->get()->row();
        $last_login = date('Y-m-d H:i:s');
        $token = base64_encode(random_bytes(18));
        $expired_at = date("Y-m-d H:i:s", strtotime('+12 hours'));
        if (!isset($q) || $q == '') {
            $param['last_login'] = $last_login;
            $param['create_on'] = $last_login;
            $this->db->insert('user_details', $param);
            $uiqId = $this->db->insert_id();
            $this->db->insert('users_authentication', array('users_id' => $this->db->insert_id(), 'token' => $token, 'expired_at' => $expired_at));
            $this->db->select('name,value');
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return array('status' => 500, 'message' => 'Internal server error.');
            } else {
                $this->db->trans_commit();
                $common_prams = $this->db->select('name,value')->from('common_params')->get()->row();
                return array('status' => 200, 'message' => 'user created.', 'id' => $uiqId, 'token' => $token, 'common_params' => $common_prams);
            }
        } else if (isset($q->id)) {
            $id = $q->id;
            $this->db->trans_start();
            $this->db->where('id', $id)->update('user_details', array('last_login' => $last_login));
        //    $this->db->insert('users_authentication', array('users_id' => $id, 'token' => $token, 'expired_at' => $expired_at));
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return array('status' => 500, 'message' => 'Internal server error.');
            } else {
                $this->db->trans_commit();
                $this->abc();
                $common_prams = $this->db->select('name,value')->from('common_params')->get()->row();
                $user_prams = $this->db->select('net_balance,total_balance,reedem_balance')->from('user_balance')->get()->row();
                return array('status' => 200, 'message' => 'Successfully login.', 'id' => $id, 'token' => $token, 'common_params' => $common_prams, 'user_data' => $user_prams);
            }
        }
    }

    public function loginV2($param)
    {

        $last_login = date('Y-m-d H:i:s');
        $token = base64_encode(random_bytes(12));
        $array = null;
        $udta = null;
        $q = $this->db->select('id,username,email,uniq_key,last_login,status,device')->from('user_details')->where('email', $param['email'])->get()->row();
        if (isset($q->id)) {
            $this->db->trans_start();
            $this->db->where('id', $q->id)->update('user_details', array('last_login' => $last_login));
            $this->db->where('id', $q->id)->update('users_authentication', array('user_id' => $q->id, 'token' => $token));
            $this->db->trans_complete();
            $udta = $q;
            $array = array('status' => 200, 'message' => 'Successfully login.');
        } else {
            $uniqKey = $this->getToken(8);
            $param['last_login'] = $last_login;
            $param['create_on'] = $last_login;
            $param['uniq_key'] = $uniqKey;
            $this->db->trans_start();
            $this->db->insert('user_details', $param);
            $uiqId = $this->db->insert_id();
            $this->db->insert('user_flag', array('user_id' => $uiqId));
            $this->db->insert('user_balance', array('user_id' => $uiqId));
            $this->db->insert('users_authentication', array('user_id' => $uiqId, 'token' => $token));
            $this->db->trans_complete();
            $param['id'] = $uiqId;
            $param['token'] = $token;
            $udta = $this->array_to_object($param);
            $array = array('status' => 200, 'message' => 'user created.');
        }
        if (isset($array) && isset($udta)) {
            $user_prams = $this->db->select('net_balance,total_balance,reedem_balance')->from('user_balance')->where('user_id', $udta->id)->get()->row();
            $user_flag = $this->db->select('*')->from('user_flag')->where('user_id', $udta->id)->get()->row();
            $common_prams = $this->db->select('name,value,status,type')->from('common_params')->get()->result();
            $array['data'] = $this->getUserResponseData($common_prams, $user_flag, $user_prams, $udta);
        } else {
            $array = array('status' => 300, 'message' => 'bad reuest.');
        }
        return $array;
    }

    private function array_to_object($array)
    {
        return (object)$array;
    }

    private function object_to_array($object)
    {
        return (array)$object;
    }

    private function getObj($arr, $type)
    {
        $temp = [];
        if (isset($arr) && count($arr) > 0) {
            foreach ($arr as $key => $value) {
                if ($value->status == '1' && $value->type == $type) {
                    $temp[$value->name] = $value->value;
                }
            }
        }
        return $temp;
    }

    private function getUserResponseData($common_prams, $flags, $balance, $user)
    {
        $temp = [];

        if (isset($user)) {
            $temp['user'] = array(
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'device' => $user->device
            );

            $temp['token'] = array(
                'token' => $user->token,
                'unique_id' => $user->uniq_key,
                'last_login' => $user->last_login
            );
        }


        if (isset($balance)) {
            $temp['balance'] = $balance;
        }

        if (isset($common_prams)) {
            $temp['tag'] = $this->getObj($common_prams, '1');
            $temp['advertise'] = $this->getObj($common_prams, '2');
            $temp['app_setting'] = $this->getObj($common_prams, '3');
        }

        if (isset($flags)) {
            $temp['flag'] = $flags;
        }

        return $temp;
    }

    function getToken($length = 5)
    {
        $token = "";
        $codeAlphabet = "abcGHIJKdefghij01234klmnABCDEFGHIJKpq78912345rstuvwxyzLMNPQRSTWXYZ";
        $codeAlphabet .= "0123456789";
        $max = strlen($codeAlphabet); // edited
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[random_int(0, $max - 1)];
        }
        return $token;
    }
}
