<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'my_user';

    /**
     * 根据用户花名拼音获取用户信息
     * @param $rtx
     * @return mixed
     */
    public static function getByRTX($rtx)
    {
        $user = self::where(['rtx'=>$rtx,'ischeck'=>1])->first();
        return $user;
    }

    public static function getByEmail($email)
    {
        $user = self::where(['email'=>$email,'ischeck'=>1])->first();
        return $user;
    }
}
