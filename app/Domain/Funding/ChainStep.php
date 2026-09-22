<?php

declare(strict_types=1);

namespace App\Domain\Funding;

use Illuminate\Database\Eloquent\Model;

/**
 * A funding-chain step and the account pair it permits (Document B §2.6, RE-05).
 */
class ChainStep extends Model
{
    protected $table = 'chain_steps';

    protected $fillable = ['code', 'label', 'label_ar', 'debit_selector', 'credit_selector', 'sort_order'];
}
