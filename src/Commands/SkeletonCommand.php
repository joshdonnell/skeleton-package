<?php

declare(strict_types=1);

namespace VendorName\Skeleton\Commands;

use Illuminate\Console\Command;

final class SkeletonCommand extends Command
{
    protected $signature = 'skeleton';

    protected $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
