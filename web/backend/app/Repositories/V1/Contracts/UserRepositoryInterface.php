<?php

namespace App\Repositories\V1\Contracts;

use App\Models\User;

/**
 * ユーザーテーブルへのデータアクセスの契約
 */
interface UserRepositoryInterface
{
    /**
     * 排他ロック付きでユーザーを取得する
     *
     * @param int $id ユーザーID
     * @return User|null
     */
    public function findByIdForUpdate(int $id): ?User;

    /**
     * ログインIDの重複を確認する（論理削除済みを含む）
     *
     * @param string $loginId 確認対象のログインID
     * @param int|null $excludeId 除外するユーザーID（自身の更新時に使用）
     * @return bool
     */
    public function existsByLoginId(string $loginId, ?int $excludeId = null): bool;

    /**
     * メールアドレスの重複を確認する（論理削除済みを含む）
     *
     * @param string $email 確認対象のメールアドレス
     * @param int|null $excludeId 除外するユーザーID（自身の更新時に使用）
     * @return bool
     */
    public function existsByEmail(string $email, ?int $excludeId = null): bool;

    /**
     * ユーザー情報を更新する
     *
     * @param User $user 更新対象のユーザー
     * @param array $data 更新データ（snake_caseカラム名）
     * @return void
     */
    public function update(User $user, array $data): void;

    /**
     * ユーザーを論理削除する
     *
     * @param User $user 削除対象のユーザー
     * @return void
     */
    public function delete(User $user): void;
}
