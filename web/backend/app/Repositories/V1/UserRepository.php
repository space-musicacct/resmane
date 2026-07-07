<?php

namespace App\Repositories\V1;

use App\Models\User;
use App\Repositories\V1\Contracts\UserRepositoryInterface;

/**
 * ユーザーテーブルへのデータアクセスを担当する
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * 排他ロック付きでユーザーを取得する
     *
     * @param int $id ユーザーID
     * @return User|null
     */
    public function findByIdForUpdate(int $id): ?User
    {
        return User::where('id', $id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * ログインIDの重複を確認する（論理削除済みを含む）
     *
     * @param string $loginId 確認対象のログインID
     * @param int|null $excludeId 除外するユーザーID（自身の更新時に使用）
     * @return bool
     */
    public function existsByLoginId(string $loginId, ?int $excludeId = null): bool
    {
        $query = User::withTrashed()->where('login_id', $loginId);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * メールアドレスの重複を確認する（論理削除済みを含む）
     *
     * @param string $email 確認対象のメールアドレス
     * @param int|null $excludeId 除外するユーザーID（自身の更新時に使用）
     * @return bool
     */
    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $query = User::withTrashed()->where('email', $email);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * ユーザー情報を更新する
     *
     * @param User $user 更新対象のユーザー
     * @param array $data 更新データ（snake_caseカラム名）
     * @return void
     */
    public function update(User $user, array $data): void
    {
        $user->update($data);
    }

    /**
     * ユーザーを論理削除する
     *
     * @param User $user 削除対象のユーザー
     * @return void
     */
    public function delete(User $user): void
    {
        $user->delete();
    }
}
