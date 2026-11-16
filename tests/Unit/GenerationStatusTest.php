<?php

use App\Enums\GenerationStatus as Status;

it('only moves forward', function (Status $from, Status $to, bool $allowed) {
    expect($from->canAdvanceTo($to))->toBe($allowed);
})->with([
    [Status::Pending, Status::Queued, true],
    [Status::Pending, Status::Succeeded, true],
    [Status::Queued, Status::Processing, true],
    [Status::Processing, Status::Succeeded, true],
    [Status::Processing, Status::Failed, true],
    [Status::Queued, Status::Queued, false],
    [Status::Processing, Status::Queued, false],
    [Status::Succeeded, Status::Processing, false],
    [Status::Succeeded, Status::Failed, false],
    [Status::Failed, Status::Succeeded, false],
]);
