<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Operation extends CI_Model
{
    public function addSupportRequest($user_id, $param)
    {
        try {
            if (isset($user_id) && isset($param['subject']) && isset($param['dec'])) {
                $ar = array(
                    'user_id' => $user_id,
                    'subject' => $param['subject'],
                    'desc' => $param['dec'],
                    'date' => date('Y-m-d H:i:s')
                );
                $this->db->insert('support', $ar);
                if ($this->db->insert_id() > 0) {
                    json_output(200, array('status' => 200, 'message' => 'sucess.'));
                } else {
                    json_output(400, array('status' => 400, 'message' => 'operation fail.'));
                }
            } else {
                throw new Exception("operation fai, invalid data.");
            }
        } catch (Exception $e) {
            json_output(400, array('status' => 400, 'message' => $e->getMessage()));
        }

    }

    public function addRedeemRequest($user_id, $param)
    {
        try {
            if (isset($user_id) && isset($param->card_id) && (isset($param->paytm) || isset($param->paypal))) {
                $ar = array(
                    'user_id' => $user_id,
                    'gift_card_id' => $param->card_id,
                    'name' => isset($param->name) ? $param->name : null,
                    'paytm' => isset($param->paytm) ? $param->paytm : null,
                    'paypal' => isset($param->paypal) ? $param->paypal : null,
                    'date' => date('Y-m-d H:i:s')
                );
                //TO-DO validate to check is suficient balance is avialable or not.
                $this->db->insert('redeem_request', $ar);
                if ($this->db->insert_id() > 0) {
                    json_output(200, array('status' => 200, 'message' => 'sucess.'));
                } else {
                    json_output(400, array('status' => 400, 'message' => 'operation fail.'));
                }
            } else {
                throw new Exception("operation fai, invalid data.");
            }
        } catch (Exception $e) {
            json_output(400, array('status' => 400, 'message' => $e->getMessage()));
        }
    }

    public function addPoints($tagid, $userid)
    {
        $dt = $this->getTagDetails($tagid);
        if (isset($dt)) {
            $this->db->trans_start();
            $this->addPointsCreditHistory($userid, $dt->points, $tagid, $dt->tag_name);
            $points = $this->addBalanceOrGet($userid, $dt->points);
            $this->db->trans_complete();
            if (isset($points)) {
                json_output(200, array('status' => 200, 'message' => 'sucess.', 'data' => $points));
            } else {
                json_output(400, array('status' => 409, 'message' => 'fail'));
            }
        } else {
            json_output(400, array('status' => 409, 'message' => 'invalid tag'));
        }
    }

    public function addParentPoints($userid, $parentcode)
    {
        $query = $this->db->select('id')->from('user_flag')->where('parent_code_verify', 0)->where('user_id', $userid)->get()->row();
        if (isset($query)) {
            $q = $this->db->select('id')->from('user_details')->where('uniq_key', $parentcode)->get()->row();
            if (isset($q) && $q->id != $userid) {
                $this->db->trans_start();
                $this->db->insert('user_friends', array('user_id' => $q->id, 'friends_id' => $userid));
                $this->db->where('id', $userid)->update('user_details', array('parent_code' => $parentcode));
                $this->db->where('user_id', $userid)->update('user_flag', array('parent_code_verify' => 1));
                //TO-DO
                $ad = $this->addBalanceOrGet($userid, 200);
                //TO-DO
                $this->addBalanceOrGet($q->id, 200);
                $this->db->trans_complete();
                json_output(200, array('status' => 200, 'message' => 'sucess.', 'data' => $ad));
            } else {
                json_output(400, array('status' => 400, 'message' => 'invalid parent code'));
            }
        } else {
            json_output(400, array('status' => 400, 'message' => 'parent code already maped.'));
        }
    }

    public function addBalanceOrGet($id, $point)
    {
        $b = $this->getUserBalance($id);
        $points = null;
        //TO-DO
        if (isset($b)) {
            $to = $b->total_balance + $point;
            $net = $b->net_balance + $point;
            if (isset($b)) {
                $points = array('net_balance' => $net, 'total_balance' => $to,'reedem_balance'=>$b->reedem_balance);
                $this->db->where('user_id', $id)->update('user_balance', $points);
                return $points;
            }
        }
        return null;
    }

    public function redeemBalance($id, $point)
    {
        $b = $this->getUserBalance($id);
        $points = null;
        if (isset($b) && isset($point)) {
            $to = $b->total_balance;
            $net = $b->net_balance - $point;
            $rd = $b->reedem_balance + $point;
            $points = array('net_balance' => $net, 'total_balance' => $to, 'reedem_balance' => $rd);
            $this->db->where('user_id', $id)->update('user_balance', $points);
            return $points;
        }
        return $points;
    }

    private function getUserBalance($id)
    {
        return $this->db->from('user_balance')->where('user_id', $id)->get()->row();
    }

    public function addPointsCreditHistory($userid, $point, $tagid, $tagname)
    {
        $ar = array(
            'user_id'=>$userid,
            'tag_name'=>$tagname,
            'tag_id'=>$tagid,
            'credit'=>$point,
            'date'=>date('Y-m-d H:i:s')
        );
        $this->db->insert('credit_history', $ar);
    }

    public function getTagDetails($tagid)
    {
        if (isset($tagid)) {
            return $this->db->from('tags')->where('tag_id', $tagid)->get()->row();
        }
        return null;
    }
}