<?php

namespace App\Http\Controllers;

use App\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ApiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        $method = $request->json('method');
        switch ($method) {
            case 'email':
                $ret = $this->dealEmail($request->json('data'));
                return response()->json($ret);
            case 'rtx':
                $ret = $this->dealRtx($request->json('data'));
                return response()->json($ret);
            case 'wechat':
                $ret = $this->dealWechat($request->json('data'));
                return response()->json($ret);
            default:
                return response()->json(['status' => 400, 'info' => '不支持该类型消息', 'data' => '']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function dealEmail($data)
    {
        if (!empty($data['from'])) {
            if (is_array($data['from'])) {
                foreach ($data['from'] as $v) {
                    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        return ['status' => 400, 'info' => "发送者存在非法的邮箱格式，{$v}", 'data' => ''];
                    }
                }
            } else {
                if (!filter_var($data['from'], FILTER_VALIDATE_EMAIL)) {
                    return ['status' => 400, 'info' => "发送者存在非法的邮箱格式，{$data['from']}", 'data' => ''];
                }
            }
        }
        if (empty($data['to'])) {
            return ['status' => 400, 'info' => '接收者邮箱不能为空', 'data' => ''];
        }
        if (is_array($data['to'])) {
            foreach ($data['to'] as $v) {
                if (!filter_var($v, FILTER_VALIDATE_EMAIL) || !Users::getByEmail($v)) {
                    return ['status' => 400, 'info' => "接收者存在非法的邮箱格式或邮箱不存在，{$v}", 'data' => ''];
                }
            }
        } else {
            if (!filter_var($data['to'], FILTER_VALIDATE_EMAIL) || !Users::getByEmail($data['to'])) {
                return ['status' => 400, 'info' => "接收者存在非法的邮箱格式或邮箱不存在，{$data['to']}", 'data' => ''];
            }
        }
        if (empty($data['title'])) {
            return ['status' => 400, 'info' => '邮件标题不能为空', 'data' => ''];
        }
        if (empty($data['body'])) {
            return ['status' => 400, 'info' => '邮件正文不能为空', 'data' => ''];
        }
        Redis::rpush('oa:msg:email', json_encode($data));
        return ['status' => 200, 'info' => '', 'data' => ''];
    }

    public function dealRtx($data)
    {
        if (empty($data['to'])) {
            return ['status' => 400, 'info' => '接收者花名不能为空', 'data' => ''];
        }
        if (is_array($data['to'])) {
            $correctUsers = $wrongUsers = [];
            foreach ($data['to'] as $v) {
                if (!Users::getByRTX($v)) {
                    array_push($wrongUsers, $v);
                } else {
                    array_push($correctUsers, $v);
                }
            }
            // 如果有正确的花名
            if ($correctUsers) {
                $data['to'] = $correctUsers;
            }

            // 如果没有正确的花名，且错误的花名不为空
            if (empty($correctUsers) && $wrongUsers) {
                return ['status' => 400, 'info' => "接收者存在非法的花名，".implode(',', $wrongUsers), 'data' => ''];
            }
        } else {
            if (!Users::getByRTX($data['to']) && $data['to'] != 'all') {
                return ['status' => 400, 'info' => "接收者存在非法的花名，{$data['to']}", 'data' => ''];
            }
        }
        if (empty($data['title'])) {
            return ['status' => 400, 'info' => 'rtx标题不能为空', 'data' => ''];
        }
        if (empty($data['body'])) {
            return ['status' => 400, 'info' => 'rtx正文不能为空', 'data' => ''];
        }
        if (!isset($data['autoclose'])) {
            $data['autoclose'] = 0;
        } elseif (!is_int($data['autoclose'])) {
            return ['status' => 400, 'info' => '自动关闭时间必须是整数', 'data' => ''];
        }
        Redis::rpush('oa:msg:rtx', json_encode($data));

        if (isset($wrongUsers) && $wrongUsers) {
            return ['status' => 400, 'info' => "信息发送成功，但部分接收者存在非法的花名，".implode(',', $wrongUsers), 'data' => ''];
        }
        
        return ['status' => 200, 'info' => '', 'data' => ''];
    }

    public function dealWechat($data)
    {
        if (!isset($data['appid'])) {
            $data['appid'] = 0;
        } elseif (!is_int($data['appid'])) {
            return ['status' => 400, 'info' => 'appid必须为整数', 'data' => ''];
        }
        if (is_array($data['to'])) {
            $correctUsers = $wrongUsers = [];
            foreach ($data['to'] as $v) {
                if (!Users::getByRTX($v)) {
                    array_push($wrongUsers, $v);
                } else {
                    array_push($correctUsers, $v);
                }
            }
            // 如果有正确的花名
            if ($correctUsers) {
                $data['to'] = $correctUsers;
            }

            // 如果没有正确的花名，且错误的花名不为空
            if (empty($correctUsers) && $wrongUsers) {
                return ['status' => 400, 'info' => "接收者存在非法的花名，".implode(',', $wrongUsers), 'data' => ''];
            }
        } else {
            if (!Users::getByRTX($data['to']) && $data['to'] != 'all') {
                return ['status' => 400, 'info' => "接收者存在非法的花名，{$data['to']}", 'data' => ''];
            }
        }
        if (empty($data['body'])) {
            return ['status' => 400, 'info' => '微信正文不能为空', 'data' => ''];
        }
        if (!isset($data['type'])) {
            $data['type'] = 0;
        } elseif (!is_int($data['type'])) {
            return ['status' => 400, 'info' => '用户类型必须为整数', 'data' => ''];
        }
        if (!isset($data['is_private'])) {
            $data['is_private'] = 0;
        } elseif (!is_int($data['is_private'])) {
            return ['status' => 400, 'info' => '保密字段必须为整数', 'data' => ''];
        }
        Redis::rpush('oa:msg:wechat', json_encode($data));

        if (isset($wrongUsers) && $wrongUsers) {
            return ['status' => 400, 'info' => "信息发送成功，但部分接收者存在非法的花名，".implode(',', $wrongUsers), 'data' => ''];
        }

        return ['status' => 200, 'info' => '', 'data' => ''];
    }
}
