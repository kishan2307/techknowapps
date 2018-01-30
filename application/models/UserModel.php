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
                json_output(401, array('status' => 401, 'message' => 'invalid token.'.$userid));
            }
        } else {
            json_output(401, array('status' => 401, 'message' => 'userid or token empty.'));
        }
    }

    public function loginV2($param)
    {

        $last_login = date('Y-m-d H:i:s');
        $token = base64_encode(random_bytes(12));
        $array = null;
        $udta = null;
        $q = $this->db->select('id,username,email,uniq_key,last_login,status,device,parent_code')->from('user_details')->where('email', $param->email)->get()->row();
        if (isset($q->id)) {
            $this->db->trans_start();
            $this->db->where('id', $q->id)->update('user_details', array('last_login' => $last_login));
            $this->db->where('user_id', $q->id)->update('users_authentication', array('token' => $token));
            $this->db->trans_complete();
            $udta = $q;
            $udta->token = $token;
            $array = array('status' => 200, 'message' => 'Successfully login.');
        } else {
            $uniqKey = $this->getToken(8);
            $param->last_login = $last_login;
            $param->create_on = $last_login;
            $param->uniq_key = $uniqKey;
            $this->db->trans_start();
            $this->db->insert('user_details', $param);
            $uiqId = $this->db->insert_id();
            if (isset($uiqId)) {
            $this->db->insert('user_flag', array('user_id' => $uiqId));
            $this->db->insert('user_balance', array('user_id' => $uiqId));
            $this->db->insert('users_authentication', array('user_id' => $uiqId, 'token' => $token));
            $this->db->trans_complete();
                $param->id = $uiqId;
                $param->token = $token;
                $param->parent_code = "";
                $udta = $param;
            $array = array('status' => 200, 'message' => 'user created.');
        }
        }

        if ($this->db->trans_status() == false) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        if (isset($array) && isset($udta)) {
                $array['data'] = $this->getCommonData($udta);
        } else {
            $array = array('status' => 300, 'message' => 'bad reuest.');
        }
        }
        return $array;
    }

    private function getCommonData($udta)
    {
        $user_prams = $this->db->select('net_balance,total_balance,reedem_balance')->from('user_balance')->where('user_id', $udta->id)->get()->row();
        $user_flag = $this->db->select('*')->from('user_flag')->where('user_id', $udta->id)->get()->row();
        $common_prams = $this->db->select('name,value,status,type')->from('common_params')->get()->result();
        return $this->getUserResponseData($common_prams, $user_flag, $user_prams, $udta);
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
                'unique_id' => base64_encode($user->id),
                'email' => $user->email,
                'username' => $user->username,
                'device' => $user->device,
                'invitation_code' => $user->uniq_key,
                'parent_code' => $user->parent_code
            );

            $temp['token'] = array(
                'token' => $user->token,
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
