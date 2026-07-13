<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Tickets\CheckInTicketRequest;
use App\Services\Tickets\CheckIn\Data\CheckInTicketData;
use App\Services\Tickets\CheckIn\TicketCheckInService;
use Illuminate\Http\JsonResponse;

class TicketCheckInController extends BaseApiController
{
    public function __construct(
        private readonly TicketCheckInService $ticketCheckInService,
    ) {}

    public function store(CheckInTicketRequest $request): JsonResponse
    {
        $result = $this->ticketCheckInService->checkIn(new CheckInTicketData(
            qrToken: $request->qrToken(),
            checkedInByUserId: (int) $request->user()->id,
            gateId: $request->gateId(),
            deviceId: $request->deviceId(),
            notes: $request->notes(),
        ));

        return $this->respondSuccess($result->toArray());
    }
}
