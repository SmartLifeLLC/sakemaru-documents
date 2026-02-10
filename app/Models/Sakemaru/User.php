<?php

namespace App\Models\Sakemaru;

use App\Models\User as BaseUser;

/**
 * WMS/Trade セッション互換性のためのエイリアス。
 * WMS はセッションに 'App\Models\Sakemaru\User' のクラス名で保存するため、
 * このクラスが存在しないとセッションデシリアライズが失敗する。
 */
class User extends BaseUser
{
    // BaseUser の機能をすべて継承
}
