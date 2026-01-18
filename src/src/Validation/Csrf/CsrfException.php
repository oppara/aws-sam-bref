<?php

declare(strict_types=1);

namespace App\Validation\Csrf;

/**
 * CSRF検証例外クラス
 *
 * CSRF（クロスサイトリクエストフォージェリ）トークンの検証に失敗した場合に発生する例外。
 *
 * @package App\Validation\Csrf
 */
class CsrfException extends \RuntimeException
{
}
