<?php

namespace App\Services\V1;

use App\Models\User;
use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use App\Repositories\V1\Contracts\UpperLimitSettingRepositoryInterface;
use App\Repositories\V1\Contracts\UserRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * ユーザー情報の編集・退会に関するビジネスロジックを担当する
 */
readonly class UserService
{
    /**
     * @param UserRepositoryInterface $userRepository
     * @param KakeiboRecordRepositoryInterface $kakeiboRecordRepository
     * @param SelfReviewRepositoryInterface $selfReviewRepository
     * @param PostRepositoryInterface $postRepository
     * @param UpperLimitSettingRepositoryInterface $upperLimitSettingRepository
     */
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private KakeiboRecordRepositoryInterface $kakeiboRecordRepository,
        private SelfReviewRepositoryInterface $selfReviewRepository,
        private PostRepositoryInterface $postRepository,
        private UpperLimitSettingRepositoryInterface $upperLimitSettingRepository,
    ) {}

    /**
     * 認証済みユーザーを排他ロック付きで取得する
     *
     * @param User|null $sessionUser セッションから取得したユーザー（null時は401）
     * @return User 排他ロック取得済みのユーザー
     */
    public function findAuthUserOrFail(?User $sessionUser): User
    {
        if (!$sessionUser) {
            abort(Response::HTTP_UNAUTHORIZED, 'ログインが必要です');
        }

        $user = $this->userRepository->findByIdForUpdate($sessionUser->id);

        if (!$user) {
            abort(Response::HTTP_UNAUTHORIZED, 'ログインが必要です');
        }

        return $user;
    }

    /**
     * ユーザー情報を更新する
     *
     * loginId/emailの重複チェック、パスワード変更時の現パスワード照合を含む。
     * 排他ロック付きトランザクション内で処理する。
     *
     * @param User|null $sessionUser セッションから取得したユーザー
     * @param array $validated バリデーション済みリクエストデータ（camelCase）
     * @return array{user: User}|array{error: string, status: int} 成功時はuser、失敗時はerrorとstatus
     * @throws QueryException DB操作に失敗した場合
     * @throws Throwable トランザクション内で例外が発生した場合
     */
    public function update(?User $sessionUser, array $validated): array
    {
        return DB::transaction(function () use ($sessionUser, $validated) {
            $user = $this->findAuthUserOrFail($sessionUser);

            if (isset($validated['loginId']) && $this->userRepository->existsByLoginId($validated['loginId'], $user->id)) {
                return ['error' => 'このログインIDは既に使用されています', 'status' => Response::HTTP_CONFLICT];
            }

            if (isset($validated['email']) && $this->userRepository->existsByEmail($validated['email'], $user->id)) {
                return ['error' => 'このメールアドレスは既に使用されています', 'status' => Response::HTTP_CONFLICT];
            }

            if (isset($validated['password'])) {
                if (!Hash::check($validated['currentPassword'], $user->password_hash)) {
                    return ['error' => '現在のパスワードが正しくありません', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY];
                }
            }

            $updateData = [];

            if (isset($validated['loginId'])) {
                $updateData['login_id'] = $validated['loginId'];
            }
            if (isset($validated['email'])) {
                $updateData['email'] = $validated['email'];
            }
            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            if (isset($validated['password'])) {
                $updateData['password_hash'] = Hash::make($validated['password']);
            }

            if (!empty($updateData)) {
                $this->userRepository->update($user, $updateData);
            }

            return ['user' => $user];
        });
    }

    /**
     * ユーザーを退会させる
     *
     * パスワード照合後、関連データ（家計簿・レビュー・投稿・基準値設定）を
     * すべて論理削除してからユーザー自身を論理削除する。
     * 排他ロック付きトランザクション内で処理する。
     *
     * @param User|null $sessionUser セッションから取得したユーザー
     * @param string $currentPassword 確認用の現在のパスワード
     * @return array{error: string, status: int}|null 失敗時はerrorとstatus、成功時はnull
     * @throws QueryException DB操作に失敗した場合
     * @throws Throwable トランザクション内で例外が発生した場合
     */
    public function destroy(?User $sessionUser, string $currentPassword): ?array
    {
        return DB::transaction(function () use ($sessionUser, $currentPassword) {
            $user = $this->findAuthUserOrFail($sessionUser);

            if (!Hash::check($currentPassword, $user->password_hash)) {
                return ['error' => '現在のパスワードが正しくありません', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY];
            }

            $recordIds = $this->kakeiboRecordRepository->pluckIdsByUserId($user->id);

            if ($recordIds->isNotEmpty()) {
                $this->selfReviewRepository->deleteByRecordIds($recordIds);
                $this->postRepository->deleteByRecordIds($recordIds);
                $this->kakeiboRecordRepository->deleteByIds($recordIds);
            }

            $this->upperLimitSettingRepository->deleteByUserId($user->id);
            $this->userRepository->delete($user);

            return null;
        });
    }
}
