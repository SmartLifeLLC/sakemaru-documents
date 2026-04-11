# LINE風チャットUI 実装仕様書

他システムへの横展開用。Tailwind CSS + Livewire（またはAlpine.js）で LINE 風チャットUIを実装するための設計仕様。

---

## 1. 全体構成

チャットパネルは3つのセクションで構成される。上から順に固定レイアウト。

```
┌──────────────────────────────────────────┐
│ ステータスバー（rounded-t-xl）            │  ← 固定高さ
├──────────────────────────────────────────┤
│                                          │
│  チャットメッセージ領域                     │  ← スクロール可能
│  bg: #7494C0（LINE風ブルーグレー）          │     height: 480px
│                                          │
├──────────────────────────────────────────┤
│ 入力エリア（rounded-b-xl）                │  ← 固定（高さ可変）
└──────────────────────────────────────────┘
```

全体を `rounded-xl` + `border` で1つのカードとしてまとめる。
ステータスバーに `rounded-t-xl`、入力エリアに `rounded-b-xl` を付与。

---

## 2. カラーパレット

### 2-1. チャット背景

| モード | カラーコード | 用途 |
|--------|-------------|------|
| Light  | `#7494C0`   | メッセージ領域背景 |
| Dark   | `#2C3E50`   | メッセージ領域背景（ダークモード） |

### 2-2. メッセージバブル

| 種類 | Light | Dark | 備考 |
|------|-------|------|------|
| 自分（管理側） | `#A8D97F`（緑） | `#5B8C3E` | LINE 送信メッセージと同系統 |
| 相手（顧客側） | `#FFFFFF`（白） | Tailwind `gray-700` | LINE 受信メッセージと同系統 |
| システム | `bg-black/20` + `backdrop-blur-sm` | 同左 | 半透明の丸型ラベル |

### 2-3. テキスト色

| 要素 | クラス |
|------|--------|
| バブル内テキスト | `text-gray-900 dark:text-gray-100` |
| 発言者名 | `text-white/80`（背景に対してのコントラスト） |
| 時刻 | `text-white/60` |
| システムメッセージ本文 | `text-white` |
| システムメッセージ時刻 | `text-white/60` |

### 2-4. ステータス色

| ステータス | HEX | Tailwind bg（薄い背景用） |
|-----------|-----|--------------------------|
| open（受付） | `#f59e0b` | `bg-amber-50 / dark:bg-amber-950/30` |
| in_review（確認中） | `#3b82f6` | `bg-blue-50 / dark:bg-blue-950/30` |
| resolved（完了） | `#10b981` | `bg-emerald-50 / dark:bg-emerald-950/30` |
| rejected（却下） | `#ef4444` | `bg-red-50 / dark:bg-red-950/30` |
| canceled（取消） | `#6b7280` | `bg-gray-50 / dark:bg-gray-800` |

---

## 3. メッセージバブル設計

### 3-1. 自分のメッセージ（右寄せ）

```html
<div class="mb-3 flex justify-end gap-1.5">
    <div class="flex flex-col items-end">
        <!-- 発言者名 -->
        <span class="mb-1 text-[11px] font-medium text-white/80">名前</span>
        <div class="flex items-end gap-1">
            <!-- 時刻（バブルの左） -->
            <span class="mb-0.5 text-[10px] text-white/60">14:30</span>
            <!-- バブル -->
            <div class="relative max-w-xs rounded-2xl rounded-tr-sm bg-[#A8D97F] px-3.5 py-2.5 shadow-sm sm:max-w-sm md:max-w-md">
                <p class="whitespace-pre-wrap break-words text-[13px] leading-relaxed text-gray-900">本文</p>
            </div>
        </div>
    </div>
</div>
```

**ポイント:**
- `rounded-2xl rounded-tr-sm` → 右上だけ角を小さくして吹き出し感を出す
- 時刻はバブルの**左下**（自分のメッセージ）
- `max-w-xs sm:max-w-sm md:max-w-md` でレスポンシブに最大幅を制御
- アバターアイコンは表示しない（自分のメッセージのため）

### 3-2. 相手のメッセージ（左寄せ）

```html
<div class="mb-3 flex justify-start gap-1.5">
    <!-- アバター -->
    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-gray-500 shadow-sm">
        頭文字
    </div>
    <div class="flex flex-col">
        <!-- 発言者名 -->
        <span class="mb-1 text-[11px] font-medium text-white/80">名前</span>
        <div class="flex items-end gap-1">
            <!-- バブル -->
            <div class="relative max-w-xs rounded-2xl rounded-tl-sm bg-white px-3.5 py-2.5 shadow-sm sm:max-w-sm md:max-w-md">
                <p class="whitespace-pre-wrap break-words text-[13px] leading-relaxed text-gray-900">本文</p>
            </div>
            <!-- 時刻（バブルの右） -->
            <span class="mb-0.5 text-[10px] text-white/60">14:30</span>
        </div>
    </div>
</div>
```

**ポイント:**
- `rounded-2xl rounded-tl-sm` → 左上だけ角を小さくして吹き出し感を出す
- 時刻はバブルの**右下**（相手のメッセージ）
- アバターは名前の頭文字1文字（`mb_substr($name, 0, 1)`）
- アバターサイズ: `h-9 w-9`、`rounded-full`

### 3-3. システムメッセージ（中央）

```html
<div class="my-3 flex justify-center">
    <div class="rounded-full bg-black/20 px-4 py-1 backdrop-blur-sm">
        <span class="text-xs text-white">本文</span>
        <span class="ml-2 text-[10px] text-white/60">14:30</span>
    </div>
</div>
```

**ポイント:**
- `rounded-full` + `bg-black/20` + `backdrop-blur-sm` でフローティングラベル
- 日付変更線やステータス変更通知に使用

### 3-4. 空メッセージ状態

```html
<div class="flex h-full items-center justify-center">
    <div class="rounded-full bg-black/20 px-5 py-2 backdrop-blur-sm">
        <span class="text-sm text-white/70">メッセージはまだありません</span>
    </div>
</div>
```

---

## 4. ステータスバー設計

チャット領域の上部に配置。ステータス表示と変更操作を1行にまとめる。

```html
<div class="flex items-center justify-between rounded-t-xl border border-gray-200 px-4 py-3 {statusBgClass}">
    <!-- 左: ステータス表示 -->
    <div class="flex items-center gap-2">
        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {statusHex}"></span>
        <span class="text-sm font-semibold text-gray-800">ラベル</span>
    </div>
    <!-- 右: 変更操作（遷移可能時のみ表示） -->
    <div class="flex items-center gap-2">
        <select class="rounded-lg border-gray-300 bg-white py-1.5 pl-3 pr-8 text-xs font-medium ...">
            <option value="">変更先を選択</option>
            ...
        </select>
        <button class="rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white ...">
            変更
        </button>
    </div>
</div>
```

**ポイント:**
- ステータスに応じた薄い背景色（Section 2-4 参照）
- 色付きドット（`h-2.5 w-2.5 rounded-full`）でステータスを視覚化
- 遷移不可なステータス（`resolved`, `rejected`, `canceled`）ではドロップダウンを非表示

---

## 5. 入力エリア設計

```html
<div class="rounded-b-xl border border-t-0 border-gray-200 bg-gray-50 px-3 py-3">
    <form class="flex items-end gap-2">
        <div class="flex-1">
            <textarea
                placeholder="メッセージを入力..."
                rows="1"
                maxlength="5000"
                class="block w-full resize-none rounded-2xl border-gray-300 bg-white px-4 py-2.5 text-sm shadow-sm ..."
            ></textarea>
        </div>
        <button class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-500 text-white shadow-sm ...">
            <!-- 紙飛行機 SVG アイコン -->
        </button>
    </form>
</div>
```

**ポイント:**
- テキストエリアは `rounded-2xl` で丸い入力フィールド
- `resize-none` + Alpine.js で自動リサイズ（最大120px）
- 送信ボタンは `rounded-full h-10 w-10` の丸型
- Enter で送信、Shift+Enter で改行

### 5-1. 自動リサイズ（Alpine.js）

```html
<textarea
    x-data="{ resize() { $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'; } }"
    x-on:input="resize()"
    x-on:keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage(); }"
></textarea>
```

### 5-2. 送信ボタンアイコン（Heroicon: paper-airplane）

```html
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
    <path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.897 28.897 0 0 0 15.293-7.155.75.75 0 0 0 0-1.114A28.897 28.897 0 0 0 3.105 2.288Z" />
</svg>
```

---

## 6. 時刻フォーマット

| 条件 | フォーマット | 例 |
|------|-------------|-----|
| 今日 | `H:i` | `14:30` |
| 今年（今日以外） | `n/j H:i` | `2/25 14:30` |
| 去年以前 | `Y/n/j H:i` | `2025/12/1 9:00` |

```php
public static function formatTime(?string $datetime): string
{
    if (! $datetime) return '';
    $carbon = Carbon::parse($datetime);
    $today = Carbon::today();

    if ($carbon->isSameDay($today)) return $carbon->format('H:i');
    if ($carbon->year === $today->year) return $carbon->format('n/j H:i');
    return $carbon->format('Y/n/j H:i');
}
```

---

## 7. 自動スクロール

ページ読み込み時と新しいメッセージ送信後に、チャット領域を最下部にスクロールする。

```javascript
// Livewire の場合
$wire.on('commentsUpdated', () => {
    $nextTick(() => {
        const el = document.getElementById('chat-messages');
        if (el) el.scrollTop = el.scrollHeight;
    });
});

// 初回読み込み
$nextTick(() => {
    const el = document.getElementById('chat-messages');
    if (el) el.scrollTop = el.scrollHeight;
});
```

---

## 8. コメントデータ構造

テンプレートが期待するコメントオブジェクトの形式:

```php
[
    'commenter_type' => 'company_user' | 'partner_user' | 'system',
    'commenter_id'   => 123,
    'commenter_name' => '田中太郎',       // 名前解決済み
    'body'           => 'メッセージ本文',
    'created_at'     => '2026-02-25 14:30:00',
]
```

### 8-1. commenter_type の判定ルール

| `commenter_type` | 配置 | バブル色 | アバター |
|-------------------|------|---------|---------|
| `company_user`（自分） | 右寄せ | 緑 `#A8D97F` | なし |
| `partner_user`（相手） | 左寄せ | 白 `#FFFFFF` | 頭文字アイコン |
| `system` | 中央 | 半透明 `bg-black/20` | なし |

**「自分」の判定**: 実装するシステムが管理側なら `company_user` が自分。パートナー側なら `partner_user` が自分。判定ロジックは各システムで変数化する。

### 8-2. コメント者名の解決

名前が取得できない場合のフォールバック: `{commenter_type}#{commenter_id}`

```php
$comment['commenter_name'] = match ($type) {
    'company_user' => $companyNames->get($id, "company_user#{$id}"),
    'partner_user' => $partnerNames->get($id, "partner_user#{$id}"),
    'system'       => 'システム',
    default        => "{$type}#{$id}",
};
```

---

## 9. レスポンシブ対応

| ブレークポイント | バブル最大幅 | 備考 |
|----------------|-------------|------|
| default（mobile） | `max-w-xs`（320px） | |
| `sm:` | `max-w-sm`（384px） | |
| `md:` 以上 | `max-w-md`（448px） | |

チャット領域の高さは `height: 480px` 固定（`style` 属性で指定）。
必要に応じて `calc(100vh - ...)` に変更してフルハイト対応も可能。

---

## 10. ダークモード対応

すべての要素に `dark:` バリアントを付与する。主な対応箇所:

| 要素 | Light | Dark |
|------|-------|------|
| チャット背景 | `bg-[#7494C0]` | `dark:bg-[#2C3E50]` |
| 自分のバブル | `bg-[#A8D97F]` | `dark:bg-[#5B8C3E]` |
| 相手のバブル | `bg-white` | `dark:bg-gray-700` |
| アバター | `bg-white text-gray-500` | `dark:bg-gray-600 dark:text-gray-300` |
| 入力エリア背景 | `bg-gray-50` | `dark:bg-gray-800` |
| テキストエリア | `bg-white` | `dark:bg-gray-700 dark:text-white` |
| ボーダー | `border-gray-200` | `dark:border-gray-700` |

---

## 11. 技術スタック別の適用ガイド

### 11-1. Laravel + Livewire（本実装）

- Livewire コンポーネントでコメントデータ取得・送信処理
- `wire:submit`, `wire:model`, `wire:click` でバインド
- Alpine.js `x-on:keydown.enter.prevent` で Enter 送信
- `@script` ディレクティブで自動スクロール JS 配置

### 11-2. Vue.js / Nuxt への移植

- テンプレート部分は `v-for` + `v-if` に置き換え
- `wire:model` → `v-model`
- `wire:submit` → `@submit.prevent`
- `$wire.sendMessage()` → メソッド呼び出し
- 自動スクロールは `watch` + `nextTick` で実装
- Tailwind クラスはそのまま利用可能

### 11-3. React / Next.js への移植

- `@forelse` → `{comments.map(comment => ...)}`
- `className` に変換（Tailwind クラスはそのまま）
- `useRef` + `useEffect` で自動スクロール
- `useState` でフォーム状態管理
- Enter キー送信は `onKeyDown` ハンドラで実装

### 11-4. 静的 HTML（非SPA）

- サーバーサイドでHTML生成後、送信は `fetch` API で POST
- 自動スクロールは `DOMContentLoaded` イベントで実行
- Alpine.js を CDN から読み込んで自動リサイズ機能を利用

---

## 12. チェックリスト（横展開時）

- [ ] Tailwind CSS が利用可能か確認
- [ ] ダークモード対応の有無を決定
- [ ] `commenter_type` の「自分」判定ロジックを確認（管理側 or パートナー側）
- [ ] コメント者名解決のデータソースを確認
- [ ] 時刻フォーマットのタイムゾーン設定を確認
- [ ] ステータスのワークフロー（遷移ルール）をシステムに合わせて変更
- [ ] 送信APIのエンドポイントとパラメータを確認
- [ ] チャット領域の高さを画面に合わせて調整
- [ ] Enter送信 / Shift+Enter改行の挙動を確認
- [ ] 自動スクロールの動作確認
