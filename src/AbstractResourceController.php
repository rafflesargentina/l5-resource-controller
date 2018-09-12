<?php

namespace RafflesArgentina\ResourceController;

use Lang;
use Validator;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\View;
use Illuminate\Foundation\Http\FormRequest;

use RafflesArgentina\ResourceController\Contracts\ResourceControllerInterface;
use RafflesArgentina\ResourceController\Exceptions\ResourceControllerException;

abstract class AbstractResourceController extends BaseController
                                          implements ResourceControllerInterface
{
    /**
     * The alias for named routes.
     *
     * @var string|null
     */
    protected $alias;

    /**
     * The location for themed views.
     *
     * @var string|null
     */
    protected $theme;

    /**
     * The vendor views prefix.
     *
     * @var string|null
     */
    protected $module;

    /**
     * The prefix for named routes.
     *
     * @var string|null
     */
    protected $prefix;

    /**
     * The Repository class to instantiate.
     *
     * @var string
     */
    protected $repository;

    /**
     * The FormRequest class to instantiate.
     *
     * @var mixed|null
     */
    protected $formRequest;

    /**
     * The name of the resource.
     *
     * @var string
     */
    protected $resourceName;

    /**
     * Define if model uses SoftDeletes.
     *
     * @var boolean
     */
    protected $useSoftDeletes;

    /**
     * The info flash message key.
     *
     * @var string|null
     */
    protected $infoFlashMessageKey = 'rafflesargentina.status.info';

    /**
     * The error flash message key.
     *
     * @var string|null
     */
    protected $errorFlashMessageKey = 'rafflesargentina.status.error';

    /**
     * The success flash message key.
     *
     * @var string|null
     */
    protected $successFlashMessageKey = 'rafflesargentina.status.success';

    /**
     * The warning flash message key.
     *
     * @var string|null
     */
    protected $warningFlashMessageKey = 'rafflesargentina.status.warning';

    /**
     * Create a new AbstractResourceController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->_checkRepositoryProperty();
        $this->_checkResourceNameProperty();
        $this->_formatRouteNameAndViewPathModifiers();

        $this->repository = app()->make($this->repository);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request The request object.
     *
     * @return mixed
     */
    public abstract function index(Request $request);

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request The request object.
     *
     * @return mixed
     */
    public abstract function create(Request $request);

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request The request object.
     *
     * @throws ResourceControllerException
     *
     * @return mixed
     */
    public abstract function store(Request $request);

    /**
     * Display the specified resource.
     *
     * @param Request $request The request object.
     * @param string  $key     The model key.
     *
     * @return mixed
     */
    public abstract function show(Request $request, $key);

    /**
     * Show the form for editing the specified resource.
     *
     * @param Request $request The request object.
     * @param string  $key     The model key.
     *
     * @return mixed
     */
    public abstract function edit(Request $request, $key);

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request The request object.
     * @param string  $key     The model key.
     *
     * @throws ResourceControllerException
     *
     * @return mixed
     */
    public abstract function update(Request $request, $key);

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request The request object.
     * @param string  $key     The model key.
     *
     * @throws ResourceControllerException
     *
     * @return mixed
     */
    public abstract function destroy(Request $request, $key);

    /**
     * Get named route for the specified action.
     *
     * @param string $action The action.
     *
     * @return string
     */
    public function getRouteName($action)
    {
        return $this->alias.$this->resourceName.$action;
    }

    /**
     * Validate rules from a FormRequest instance.
     *
     * @return \Illuminate\Validation\Validator
     */
    public function validateRules()
    {
        $input = request()->all();
        $rules = [];
        $messages = [];

        if ($this->formRequest) {
            $this->formRequest = new $this->formRequest;
            $rules = $this->formRequest->rules();
            $messages = $this->formRequest->messages();
        }

        return Validator::make($input, $rules, $messages);
    }

    /**
     * Throw an exception if the view doesn't exist.
     *
     * @param string $view The view.
     *
     * @throws ResourceControllerException
     *
     * @return void
     */
    public function checkViewExists($view)
    {
        if (!View::exists($view)) {
            if (Lang::has('resource-controller.viewnotfound')) {
                $message = trans('resource-controller.viewnotfound', ['view' => '$view']);
            } else {
                $message = 'Requested page couldn\'t be loaded because the view file is missing: '.$view;
            }

            throw new ResourceControllerException($message);
        }
    }

    /**
     * Get view location for the specified action.
     *
     * @param string $action The action.
     *
     * @return string
     */
    public function getViewLocation($action)
    {
        if (request()->ajax()) {
            return $this->module.$this->theme.$this->resourceName.'ajax.'.$action;
        }

        return $this->module.$this->theme.$this->resourceName.$action;
    }

    /**
     * Get redirection route.
     *
     * @return string
     */
    public function getRedirectionRoute()
    {
        return $this->getRouteName('index');
    }

    /**
     * Get the FormRequest instance.
     *
     * @return mixed
     */
    public function getFormRequestInstance()
    {
        if (!$this->formRequest) {
            return new FormRequest;
        }
        
        return app()->make($this->formRequest);
    }

    /**
     * Redirect back with errors.
     *
     * @param \Illuminate\Validation\Validator $validator The validator instance.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectBackWithErrors($validator)
    {
        if (request()->wantsJson()) {
            return $this->validUnprocessableEntityJsonResponse($validator->errors());
        }

        return back()->withErrors($validator)->withInput();
    }

    /**
     * Return a valid 200 Success json response.
     *
     * @param string $message The response message.
     * @param array $data     The passed data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function validSuccessJsonResponse($message = 'Success', $data = [])
    {
        return response()->json(
            [
                'code' => '200',
                'message' => $message,
                'data' => $data,
                'errors' => [],
                'redirect' => route($this->getRedirectionRoute()),
            ], 200, [], JSON_PRETTY_PRINT
        );
    }

    /**
     * Return a valid 404 Not found json response.
     *
     * @param string $message The response message.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function validNotFoundJsonResponse($message = 'Not found')
    {
        return response()->json(
            [
                'code' => '404',
                'message' => $message,
                'errors' => [],
                'redirect' => route($this->getRedirectionRoute()),
            ], 404, [], JSON_PRETTY_PRINT
        );
    }

    /**
     * Return a valid 422 Unprocessable entity json response.
     *
     * @param \Illuminate\Support\MessageBag $errors  The message bag errors.
     * @param string                         $message The response message.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function validUnprocessableEntityJsonResponse(MessageBag $errors, $message = 'Unprocessable Entity')
    {
        return response()->json(
            [
                'code' => '422',
                'message' => $message,
                'errors' => $errors,
                'redirect' => route($this->getRedirectionRoute()),
            ], 422, [], JSON_PRETTY_PRINT
        );
    }

    /**
     * Format route name and view path modifiers.
     *
     * @return void
     */
    private function _formatRouteNameAndViewPathModifiers()
    {
        if ($this->alias) {
            $this->alias = str_finish($this->alias, '.');
        }

        if ($this->theme) {
            $this->theme = str_finish($this->theme, '.');
        }

        if ($this->module) {
            if (!ends_with($this->module, '::')) {
                $this->module .= '::';
            }
        }

        if ($this->prefix) {
            $this->prefix = str_finish($this->prefix, '.');
        }

        if ($this->resourceName) {
            $this->resourceName = str_finish($this->resourceName, '.');
        }
    }

    /**
     * Throw an exception if repository property is not set.
     *
     * @throws ResourceControllerException
     *
     * @return void
     */
    private function _checkRepositoryProperty()
    {
        if (!$this->repository) {
            if (Lang::has('resource-controller.propertynotset')) {
                $message = trans('resource-controller.propertynotset', ['property' => '$repository']);
            } else {
                $message = '$repository property must be set.';
            }

            throw new ResourceControllerException($message);
        }
    }

    /**
     * Throw an exception if resourceName property is not set.
     *
     * @throws ResourceControllerException
     *
     * @return void
     */
    private function _checkResourceNameProperty()
    {
        if (!$this->resourceName) {
            if (Lang::has('resource-controller.propertynotset')) {
                $message = trans('resource-controller.propertynotset', ['property' => '$resourceName']);
            } else {
                $message = '$resourceName property must be set.';
            }

            throw new ResourceControllerException($message);
        }
    }
}
