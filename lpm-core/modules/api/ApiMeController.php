<?php

class ApiMeController extends ApiControllerBase
{
    public function show()
    {
        $user = $this->user();
        $payload = $this->serializer()->user($user);
        $payload['email'] = $user->email;

        return ApiResponse::success([
            'user' => $payload,
        ]);
    }
}
