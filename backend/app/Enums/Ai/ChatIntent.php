<?php

namespace App\Enums\Ai;

enum ChatIntent: string
{
    case BookSearch = 'book.search';
    case BookDetail = 'book.detail';
    case BookRecommendation = 'book.recommendation';

    case SmallTalkGreeting = 'small_talk.greeting';
    case SmallTalkStatusCheck = 'small_talk.status_check';
    case SmallTalkThanks = 'small_talk.thanks';
    case SmallTalkGoodbye = 'small_talk.goodbye';
    case SmallTalkCapability = 'small_talk.capability';

    case UnsupportedOrder = 'unsupported.order';
    case UnsupportedPayment = 'unsupported.payment';
    case UnsupportedRefund = 'unsupported.refund';
    case UnsupportedAccount = 'unsupported.account';
    case UnsupportedNonBookProduct = 'unsupported.non_book_product';

    case Unknown = 'unknown';
}
