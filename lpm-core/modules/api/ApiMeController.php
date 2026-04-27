<?php

class ApiMeController extends ApiControllerBase
{
    public function show()
    {
        $user = $this->user();
        return ApiResponse::success([
            'user' => [
                'id' => $user->getID(),
                'name' => $user->getName(),
                'email' => $user->email,
            ],
        ]);
    }
}
