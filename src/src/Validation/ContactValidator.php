<?php
declare(strict_types=1);

namespace App\Validation;

use Respect\Validation\Validator as v;

/**
 * お問い合わせフォームのバリデーションルール定義クラス
 *
 * @package App\Validation
 */
class ContactValidator extends Validator
{
    /**
     * バリデーションルール定義を取得
     *
     * @return array<string, array<int, array<string, mixed>>> フィールド名をキーとするルール定義配列
     */
    public static function rules(): array
    {
        return [

            'name' => [
                [
                    'rule' => v::notEmpty(),
                    'message' => 'お名前を入力してください',
                ],
                [
                    'rule' => v::length(1, 50),
                    'message' => 'お名前は50文字以内で入力してください',
                ],
            ],


            'email' => [
                [
                    'rule' => v::notEmpty(),
                    'message' => 'メールアドレスを入力してください',
                ],
                [
                    'rule' => v::email(),
                    'message' => 'メールアドレスの形式が正しくありません',
                ],
            ],

            'email_cmp' => [
                [
                    'rule' => v::notEmpty(),
                    'message' => 'メールアドレス（確認用）を入力してください',
                ],
                [
                    'rule' => v::equals($_POST['email'] ?? ''),
                    'message' => 'メールアドレスが一致しません',
                ],
            ],

            'category' => [
                [
                    'rule' => v::notEmpty(),
                    'message' => 'お問い合わせカテゴリーを選択してください',
                ],
            ],

            'body' => [
                [
                    'rule' => v::notEmpty(),
                    'message' => 'お問い合わせ内容を入力してください',
                ],
                [
                    'rule' => v::length(1, 1000),
                    'message' => 'お問い合わせ内容は1000文字以内で入力してください',
                ],
            ],
        ];
    }
}
