<?php

namespace RafflesArgentina\ResourceController\Exceptions;

class ResourceControllerException extends \Exception
{
    /**
     * Report the exception.
     *
     * @return void
     */
    public function report()
    {
        //
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request The request object.
     *
     * @return mixed
     */
    public function render($request)
    {
        if ($request->wantsJson()) {
            return $this->validInternalServerErrorJsonResponse($this, $this->message);
        } else {
            return redirect()->back()->with(['rafflesargentina.status.error' => $this->message]);
        }
    }
}
