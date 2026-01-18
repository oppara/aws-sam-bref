<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * バリデータ基底クラス
 *
 * バリデーションルール定義と実行ロジックを提供する。
 */
abstract class Validator
{
    /**
     * バリデーションルール定義を取得
     *
     * サブクラスで実装し、バリデーションルール配列を返す必要がある。
     *
     * @return array<string, array<int, array<string, mixed>>> ルール定義
     */
    abstract public static function rules(): array;

    /**
     * 入力値を検証
     *
     * ルール定義に基づいて入力値を検証する。
     * 検証に合格したデータはトリミング処理を施して返却する。
     *
     * @param array $data 検証対象のデータ
     * @return array [エラー配列, クリーンなデータ配列]のタプル
     *               エラーがない場合、エラー配列は空配列
     */
    public static function validate(array $data): array
    {
        $errors = [];
        $clean  = [];

        foreach (static::rules() as $field => $validators) {
            $value = $data[$field] ?? null;

            foreach ($validators as $item) {
                if (!$item['rule']->validate($value)) {
                    $errors[$field] = $item['message'];
                    break;
                }
                else {
                    $clean[$field] = trim((string)$value);
                }
            }
        }

        return [$errors, $clean];
    }
}
