<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium;

use JayI\Impex\Domains\Message\Enums\Direction;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\StepStatus;
use JayI\Impex\Domains\Subscription\Enums\DeliveryStatus;
use JayI\Impex\Domains\Subscription\Enums\SubscriptionStatus;

/**
 * Maps Impex's states onto Atrium colours.
 *
 * Kept in one place so the pages and the dashboard widgets colour the same
 * state identically. Statuses render as Atrium status dots; `info` is kept
 * for pending - waiting to start, or waiting on a signal - and used by no
 * other state, so it reads the same in every package.
 */
final class Badges
{
    /**
     * The colour of a run or step status: `info` pending, `primary` in
     * progress, `warning` rolling back, `success` done, `danger` failed and
     * `neutral` over.
     */
    public static function forStatus(RunStatus|StepStatus $status): string
    {
        return match ($status) {
            RunStatus::Pending, RunStatus::Waiting, StepStatus::Pending => 'info',
            RunStatus::Running, StepStatus::Running => 'primary',
            RunStatus::RollingBack => 'warning',
            RunStatus::Completed, StepStatus::Completed => 'success',
            RunStatus::Failed, StepStatus::Failed => 'danger',
            RunStatus::Cancelled, StepStatus::Undone, StepStatus::Skipped => 'neutral',
        };
    }

    public static function forRun(RunStatus $status): string
    {
        return self::forStatus($status);
    }

    public static function forStep(StepStatus $status): string
    {
        return self::forStatus($status);
    }

    /**
     * A flow is enabled (`success`) or paused (`neutral`).
     */
    public static function forFlow(bool $enabled): string
    {
        return $enabled ? 'success' : 'neutral';
    }

    /**
     * A signature that verified (`success`), failed (`danger`), or was never
     * checked (`neutral`).
     */
    public static function forSignature(?bool $valid): string
    {
        return match ($valid) {
            true => 'success',
            false => 'danger',
            null => 'neutral',
        };
    }

    /**
     * Directions are not statuses, so they stay badges, and never `info`.
     */
    public static function forDirection(Direction $direction): string
    {
        return match ($direction) {
            Direction::Inbound => 'neutral',
            Direction::Outbound => 'primary',
        };
    }

    /**
     * A subscription delivering (`success`), held (`neutral`), or switched
     * off by the circuit breaker (`danger`).
     */
    public static function forSubscription(SubscriptionStatus $status): string
    {
        return match ($status) {
            SubscriptionStatus::Active => 'success',
            SubscriptionStatus::Paused => 'neutral',
            SubscriptionStatus::Disabled => 'danger',
        };
    }

    public static function forDelivery(DeliveryStatus $status): string
    {
        return match ($status) {
            DeliveryStatus::Succeeded => 'success',
            DeliveryStatus::Failed => 'danger',
        };
    }
}
