<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * テスト用 API エンドポイントの基底クラス
 *
 * Template Method パターンにより、バージョンごとのサブクラスは
 * version() のみを実装すれば全エンドポイントパスが確定する。
 *
 * V2 追加時は V2ApiEndpoint を作成し version() で 'v2' を返すだけでよい。
 * 各テストクラスの setUp() で注入先を切り替えて使用する。
 */
abstract class ApiEndpoint
{
    /**
     * APIバージョン文字列を返す
     *
     * @return string バージョン識別子
     *
     * @example 'v1'
     * @example 'v2'
     */
    abstract protected function version(): string;

    /**
     * バージョン付きプレフィックスを組み立てる
     *
     * @return string プレフィックス
     *
     * @example '/api/v1'
     */
    final protected function prefix(): string
    {
        return '/api/'.$this->version();
    }

    /**
     * ログインエンドポイントを返す
     *
     * @return string エンドポイントパス
     *
     * @example '/api/v1/login'
     */
    final public function login(): string
    {
        return $this->prefix().'/login';
    }

    /**
     * ログアウトエンドポイントを返す
     *
     * @return string エンドポイントパス
     *
     * @example '/api/v1/logout'
     */
    final public function logout(): string
    {
        return $this->prefix().'/logout';
    }

    /**
     * ユーザー登録エンドポイントを返す
     *
     * @return string エンドポイントパス
     *
     * @example '/api/v1/register'
     */
    final public function register(): string
    {
        return $this->prefix().'/register';
    }

    /**
     * 家計簿レコードエンドポイントを返す
     *
     * @return string エンドポイントパス
     *
     * @example '/api/v1/records'
     */
    final public function records(): string
    {
        return $this->prefix().'/records';
    }

    /**
     * ユーザー情報エンドポイントを返す
     *
     * @return string エンドポイントパス
     *
     * @example '/api/v1/user'
     */
    final public function user(): string
    {
        return $this->prefix().'/user';
    }

    /**
     * カテゴリ一覧エンドポイントを返す
     *
     * @return string エンドポイントパス
     *
     * @example '/api/v1/categories'
     */
    final public function categories(): string
    {
        return $this->prefix().'/categories';
    }

    /**
     * 収支区分一覧エンドポイントを返す
     *
     * @return string エンドポイントパス
     *
     * @example '/api/v1/amountTypes'
     */
    final public function amountTypes(): string
    {
        return $this->prefix().'/amountTypes';
    }

    /**
     * 基準値設定エンドポイントを返す
     *
     * @return string エンドポイントパス
     *
     * @example '/api/v1/settings/limit'
     */
    final public function settingsLimit(): string
    {
        return $this->prefix().'/settings/limit';
    }
}
