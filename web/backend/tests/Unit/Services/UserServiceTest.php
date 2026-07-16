<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use App\Repositories\V1\Contracts\UpperLimitSettingRepositoryInterface;
use App\Repositories\V1\Contracts\UserRepositoryInterface;
use App\Services\V1\UserService;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 単体テスト仕様書 2.5 UserService 対応テスト
 *
 * Repository群をモック化し、DB に依存せずビジネスロジックのみを検証する。
 * Hash::check / Hash::make はファサードモックで検証する。
 *
 * update() / destroy() は DB::transaction() で包まれているため、
 * テスト実行環境に有効なDB接続（トランザクション開始・コミットが可能な状態）が必要。
 */
class UserServiceTest extends TestCase
{
    private UserRepositoryInterface&MockInterface $userRepository;
    private KakeiboRecordRepositoryInterface&MockInterface $kakeiboRecordRepository;
    private SelfReviewRepositoryInterface&MockInterface $selfReviewRepository;
    private PostRepositoryInterface&MockInterface $postRepository;
    private UpperLimitSettingRepositoryInterface&MockInterface $upperLimitSettingRepository;
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->kakeiboRecordRepository = Mockery::mock(KakeiboRecordRepositoryInterface::class);
        $this->selfReviewRepository = Mockery::mock(SelfReviewRepositoryInterface::class);
        $this->postRepository = Mockery::mock(PostRepositoryInterface::class);
        $this->upperLimitSettingRepository = Mockery::mock(UpperLimitSettingRepositoryInterface::class);

        $this->service = new UserService(
            $this->userRepository,
            $this->kakeiboRecordRepository,
            $this->selfReviewRepository,
            $this->postRepository,
            $this->upperLimitSettingRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * user_id と password_hash を持つ User モックを作成する。
     */
    private function makeUser(int $id = 1, string $passwordHash = 'hashed-old-password'): User&MockInterface
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->password_hash = $passwordHash;

        return $user;
    }

    /**
     * SUS-001: findAuthUserOrFail: 正常取得
     */
    #[Test]
    public function SUS_001_findAuthUserOrFailで正常取得できる(): void
    {
        $sessionUser = $this->makeUser(1);
        $dbUser = $this->makeUser(1);

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($dbUser);

        $result = $this->service->findAuthUserOrFail($sessionUser);

        $this->assertSame($dbUser, $result);
    }

    /**
     * SUS-002: findAuthUserOrFail: sessionUser が null
     */
    #[Test]
    public function SUS_002_sessionUserがnullの場合は401になる(): void
    {
        $this->userRepository
            ->shouldNotReceive('findByIdForUpdate');

        $this->assertAbort(
            fn () => $this->service->findAuthUserOrFail(null),
            401
        );
    }

    /**
     * SUS-003: findAuthUserOrFail: DB にユーザーが存在しない
     */
    #[Test]
    public function SUS_003_DBにユーザーが存在しない場合は401になる(): void
    {
        $sessionUser = $this->makeUser(1);

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->assertAbort(
            fn () => $this->service->findAuthUserOrFail($sessionUser),
            401
        );
    }

    /**
     * SUS-004: update: loginId 変更成功
     */
    #[Test]
    public function SUS_004_loginId変更が成功する(): void
    {
        $sessionUser = $this->makeUser(1);
        $user = $this->makeUser(1);

        $validated = ['loginId' => 'newtaro'];

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        $this->userRepository
            ->shouldReceive('existsByLoginId')
            ->once()
            ->with('newtaro', 1)
            ->andReturn(false);

        $this->userRepository
            ->shouldReceive('update')
            ->once()
            ->with($user, ['login_id' => 'newtaro']);

        $result = $this->service->update($sessionUser, $validated);

        $this->assertSame($user, $result['user']);
    }

    /**
     * SUS-005: update: loginId 重複
     */
    #[Test]
    public function SUS_005_loginId重複の場合は409を返す(): void
    {
        $sessionUser = $this->makeUser(1);
        $user = $this->makeUser(1);

        $validated = ['loginId' => 'duplicated'];

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        $this->userRepository
            ->shouldReceive('existsByLoginId')
            ->once()
            ->with('duplicated', 1)
            ->andReturn(true);

        $this->userRepository
            ->shouldNotReceive('update');

        $result = $this->service->update($sessionUser, $validated);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(409, $result['status']);
    }

    /**
     * SUS-006: update: email 重複
     */
    #[Test]
    public function SUS_006_email重複の場合は409を返す(): void
    {
        $sessionUser = $this->makeUser(1);
        $user = $this->makeUser(1);

        $validated = ['email' => 'duplicated@example.com'];

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        $this->userRepository
            ->shouldReceive('existsByEmail')
            ->once()
            ->with('duplicated@example.com', 1)
            ->andReturn(true);

        $this->userRepository
            ->shouldNotReceive('update');

        $result = $this->service->update($sessionUser, $validated);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(409, $result['status']);
    }

    /**
     * SUS-007: update: パスワード変更成功
     */
    #[Test]
    public function SUS_007_パスワード変更が成功する(): void
    {
        $sessionUser = $this->makeUser(1, 'hashed-old-password');
        $user = $this->makeUser(1, 'hashed-old-password');

        $validated = [
            'currentPassword' => 'oldpass123',
            'password' => 'newpass123',
        ];

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        Hash::shouldReceive('check')
            ->once()
            ->with('oldpass123', 'hashed-old-password')
            ->andReturn(true);

        Hash::shouldReceive('make')
            ->once()
            ->with('newpass123')
            ->andReturn('hashed-new-password');

        $this->userRepository
            ->shouldReceive('update')
            ->once()
            ->with($user, ['password_hash' => 'hashed-new-password']);

        $result = $this->service->update($sessionUser, $validated);

        $this->assertSame($user, $result['user']);
    }

    /**
     * SUS-008: update: パスワード変更で currentPassword 不一致
     */
    #[Test]
    public function SUS_008_currentPassword不一致の場合は422を返す(): void
    {
        $sessionUser = $this->makeUser(1, 'hashed-old-password');
        $user = $this->makeUser(1, 'hashed-old-password');

        $validated = [
            'currentPassword' => 'wrongpass',
            'password' => 'newpass123',
        ];

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        Hash::shouldReceive('check')
            ->once()
            ->with('wrongpass', 'hashed-old-password')
            ->andReturn(false);

        $this->userRepository
            ->shouldNotReceive('update');

        $result = $this->service->update($sessionUser, $validated);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(422, $result['status']);
    }

    /**
     * SUS-009: update: 何も変更しない
     */
    #[Test]
    public function SUS_009_何も変更しない場合はRepositoryのupdateが呼ばれない(): void
    {
        $sessionUser = $this->makeUser(1);
        $user = $this->makeUser(1);

        $validated = [];

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        $this->userRepository
            ->shouldNotReceive('update');

        $result = $this->service->update($sessionUser, $validated);

        $this->assertSame($user, $result['user']);
    }

    /**
     * SUS-010: destroy: 正常退会
     */
    #[Test]
    public function SUS_010_正常に退会でき関連データが全て削除される(): void
    {
        $sessionUser = $this->makeUser(1, 'hashed-old-password');
        $user = $this->makeUser(1, 'hashed-old-password');

        $recordIds = collect([10, 11]);

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        Hash::shouldReceive('check')
            ->once()
            ->with('correctpass', 'hashed-old-password')
            ->andReturn(true);

        $this->kakeiboRecordRepository
            ->shouldReceive('pluckIdsByUserId')
            ->once()
            ->with(1)
            ->andReturn($recordIds);

        $this->selfReviewRepository
            ->shouldReceive('deleteByRecordIds')
            ->once()
            ->with($recordIds);

        $this->postRepository
            ->shouldReceive('deleteByRecordIds')
            ->once()
            ->with($recordIds);

        $this->kakeiboRecordRepository
            ->shouldReceive('deleteByIds')
            ->once()
            ->with($recordIds);

        $this->upperLimitSettingRepository
            ->shouldReceive('deleteByUserId')
            ->once()
            ->with(1);

        $this->userRepository
            ->shouldReceive('delete')
            ->once()
            ->with($user);

        $result = $this->service->destroy($sessionUser, 'correctpass');

        $this->assertNull($result);
    }

    /**
     * SUS-011: destroy: パスワード不一致
     */
    #[Test]
    public function SUS_011_パスワード不一致の場合は422を返す(): void
    {
        $sessionUser = $this->makeUser(1, 'hashed-old-password');
        $user = $this->makeUser(1, 'hashed-old-password');

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        Hash::shouldReceive('check')
            ->once()
            ->with('wrongpass', 'hashed-old-password')
            ->andReturn(false);

        $this->kakeiboRecordRepository
            ->shouldNotReceive('pluckIdsByUserId');
        $this->userRepository
            ->shouldNotReceive('delete');

        $result = $this->service->destroy($sessionUser, 'wrongpass');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame(422, $result['status']);
    }

    /**
     * SUS-012: destroy: 家計簿レコードがないユーザー
     */
    #[Test]
    public function SUS_012_家計簿レコードがない場合はレビュー投稿の削除をスキップする(): void
    {
        $sessionUser = $this->makeUser(1, 'hashed-old-password');
        $user = $this->makeUser(1, 'hashed-old-password');

        $recordIds = collect([]);

        $this->userRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(1)
            ->andReturn($user);

        Hash::shouldReceive('check')
            ->once()
            ->with('correctpass', 'hashed-old-password')
            ->andReturn(true);

        $this->kakeiboRecordRepository
            ->shouldReceive('pluckIdsByUserId')
            ->once()
            ->with(1)
            ->andReturn($recordIds);

        $this->selfReviewRepository
            ->shouldNotReceive('deleteByRecordIds');

        $this->postRepository
            ->shouldNotReceive('deleteByRecordIds');

        $this->kakeiboRecordRepository
            ->shouldNotReceive('deleteByIds');

        $this->upperLimitSettingRepository
            ->shouldReceive('deleteByUserId')
            ->once()
            ->with(1);

        $this->userRepository
            ->shouldReceive('delete')
            ->once()
            ->with($user);

        $result = $this->service->destroy($sessionUser, 'correctpass');

        $this->assertNull($result);
    }

    /**
     * abort() による HttpException（ステータスコード）を検証する共通アサーション。
     */
    private function assertAbort(callable $callback, int $status): void
    {
        try {
            $callback();
            $this->fail("Expected HttpException with status {$status} was not thrown.");
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }
}
