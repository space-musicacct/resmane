<?php

namespace Database\Seeders;

use App\Models\KakeiboRecord;
use App\Models\SelfReview;
use App\Models\UpperLimitSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DummyKakeiboRecordSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::where('login_id', 'taro_yamada')->firstOrFail()->id;

        $thisMonth = Carbon::today()->startOfMonth();
        $lastMonth = Carbon::today()->subMonthNoOverflow()->startOfMonth();

        // [日オフセット, amountTypeId, categoryId, amount, details]
        $lastMonthRecords = [
            // 収入
            [9, 2, 8, 85000, 'バイト代（前半）'],
            [24, 2, 8, 87000, 'バイト代（後半）'],
            [14, 2, 9, 5000, '親からお小遣い'],

            // 固定費
            [0, 1, 6, 45000, '家賃'],
            [4, 1, 6, 3200, 'スマホ代'],

            // サブスク
            [0, 1, 5, 1180, 'YouTubeプレミアム'],
            [0, 1, 5, 980, 'メンバーシップ（推し1人目）'],
            [0, 1, 5, 980, 'メンバーシップ（推し2人目）'],
            [0, 1, 5, 600, 'pixivFANBOX'],

            // 食費
            [1, 1, 1, 580, 'セブンで弁当'],
            [3, 1, 1, 890, '天下一品でラーメン'],
            [5, 1, 1, 350, 'おにぎり2個'],
            [7, 1, 1, 1200, 'サイゼで晩飯'],
            [10, 1, 1, 450, 'ファミマでパスタ'],
            [13, 1, 1, 780, '松屋で牛丼セット'],
            [16, 1, 1, 520, 'ローソンで弁当とお茶'],
            [19, 1, 1, 950, 'ラーメン二郎'],
            [22, 1, 1, 680, 'すき家で夜ご飯'],
            [26, 1, 1, 390, 'カップ麺とおにぎり'],

            // 交通費
            [0, 1, 2, 8400, '定期代（学校）'],
            [17, 1, 2, 640, '秋葉原往復'],

            // 推し活・趣味
            [2, 1, 3, 5500, '推しの新曲CD＋特典'],
            [9, 1, 3, 3800, 'アクスタ＋缶バッジ'],
            [12, 1, 3, 1500, 'スパチャ（推しの誕生日配信）'],
            [17, 1, 3, 8900, '推しのライブグッズ（Tシャツ＋タオル）'],
            [21, 1, 3, 2000, 'スパチャ（3Dお披露目）'],
            [25, 1, 3, 4200, 'ボイスパック（限定）'],

            // 交際費
            [8, 1, 4, 1500, '友達とカラオケ'],
            [20, 1, 4, 2800, 'オタク仲間と焼肉'],
        ];

        $thisMonthRecords = [
            // 収入
            [9, 2, 8, 82000, 'バイト代（前半）'],
            [19, 2, 9, 3000, 'おばあちゃんからお小遣い'],

            // 固定費
            [0, 1, 6, 45000, '家賃'],
            [4, 1, 6, 3200, 'スマホ代'],

            // サブスク
            [0, 1, 5, 1180, 'YouTubeプレミアム'],
            [0, 1, 5, 980, 'メンバーシップ（推し1人目）'],
            [0, 1, 5, 980, 'メンバーシップ（推し2人目）'],
            [0, 1, 5, 600, 'pixivFANBOX'],

            // 食費
            [1, 1, 1, 490, 'セブンでサンドイッチ'],
            [3, 1, 1, 850, '丸亀製麺'],
            [6, 1, 1, 380, 'おにぎりとお茶'],
            [8, 1, 1, 1100, 'ガストでハンバーグ'],
            [11, 1, 1, 560, 'ファミマで弁当'],
            [14, 1, 1, 920, 'CoCo壱でカレー'],
            [17, 1, 1, 750, '日高屋でチャーハンセット'],
            [20, 1, 1, 430, 'カップ麺2個'],

            // 交通費
            [0, 1, 2, 8400, '定期代（学校）'],
            [13, 1, 2, 640, '池袋往復'],

            // 推し活・趣味
            [5, 1, 3, 6800, '推しのBD（Blu-ray）予約'],
            [10, 1, 3, 3000, 'スパチャ（歌枠神回）'],
            [13, 1, 3, 12000, 'VTuberフェスチケット'],
            [15, 1, 3, 4500, 'フェス限定グッズ'],
            [18, 1, 3, 1500, 'ガチャ（ソシャゲコラボ）'],

            // 交際費
            [7, 1, 4, 1800, '学校の友達とマック'],
            [16, 1, 4, 3500, 'オタク仲間とフェス打ち上げ'],
        ];

        foreach ($lastMonthRecords as $r) {
            $date = $lastMonth->copy()->addDays($r[0]);
            if ($date->month !== $lastMonth->month) {
                $date = $lastMonth->copy()->endOfMonth();
            }

            KakeiboRecord::create([
                'user_id' => $userId,
                'purchase_date' => $date->toDateString(),
                'amount_type_id' => $r[1],
                'kakeibo_default_category_id' => $r[2],
                'amount' => $r[3],
                'details' => $r[4],
            ]);
        }

        foreach ($thisMonthRecords as $r) {
            $date = $thisMonth->copy()->addDays($r[0]);
            if ($date->isAfter(Carbon::today())) {
                continue;
            }

            KakeiboRecord::create([
                'user_id' => $userId,
                'purchase_date' => $date->toDateString(),
                'amount_type_id' => $r[1],
                'kakeibo_default_category_id' => $r[2],
                'amount' => $r[3],
                'details' => $r[4],
            ]);
        }

        // 上限値設定: 固定額 80,000円
        UpperLimitSetting::create([
            'user_id' => $userId,
            'upper_limit_type_id' => 2,
            'max_value' => 80000,
            'ave_monthly_income' => null,
        ]);

        // 自己レビュー: [details, reviewComment, evaluation]
        $reviews = [
            ['ガチャ（ソシャゲコラボ）', 'ガチャは沼…。でも推しのコラボだから仕方ない', 2],
            ['VTuberフェスチケット', '推しのフェス、最高だった！後悔はない！', 5],
            ['フェス限定グッズ', 'グッズも買えたし満足。ただ予算オーバー気味', 3],
            ['スパチャ（歌枠神回）', '神回だったからスパチャしちゃった。幸せ', 4],
            ['推しのBD（Blu-ray）予約', 'BD予約は計画的だったからOK', 4],
            ['日高屋でチャーハンセット', 'チャーハンセット、普通においしかった', 4],
            ['オタク仲間とフェス打ち上げ', '打ち上げ楽しかったけどちょっと使いすぎたかも', 3],
            ['ファミマで弁当', 'コンビニ弁当ばっかりだな…自炊したい', 2],
            ['スパチャ（推しの誕生日配信）', '推しの誕生日だから！これは必要経費', 5],
            ['推しの新曲CD＋特典', '新曲よかった。特典も嬉しい', 4],
            ['スパチャ（3Dお披露目）', '3Dお披露目は記念だから', 4],
            ['ラーメン二郎', '二郎うまかった。でもカロリーやばい', 3],
        ];

        foreach ($reviews as [$details, $comment, $evaluation]) {
            $record = KakeiboRecord::where('user_id', $userId)
                ->where('details', $details)
                ->first();

            if ($record) {
                SelfReview::create([
                    'kakeibo_record_id' => $record->id,
                    'review_comment' => $comment,
                    'evaluation' => $evaluation,
                ]);
            }
        }
    }
}
