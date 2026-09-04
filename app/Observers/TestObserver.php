<?php

namespace App\Observers;

use App\Models\Test;
use App\Services\CertificateService;
use App\Services\ContestService;
use App\Services\QuranNavigator;
use App\Services\StreakService;

class TestObserver
{
    public function __construct(
        private ContestService $contest,
        private StreakService $streaks,
        private QuranNavigator $navigator,
        private CertificateService $certificates,
    ) {}

    public function creating(Test $test): void
    {
        // Evaluate before the record is inserted
        $test->is_contest_entry = $this->contest->isActive()
            && $this->contest->testQualifies($test);
    }

    public function created(Test $test): void
    {
        $this->streaks->recordTest($test);
        $this->navigator->rememberProgress($test);
        $this->certificates->recordTest($test);
    }
}
