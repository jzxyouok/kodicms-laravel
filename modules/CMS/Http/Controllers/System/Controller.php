<?php namespace KodiCMS\CMS\Http\Controllers\System;

use Illuminate\Foundation\Bus\DispatchesCommands;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Str;
use KodiCMS\API\Exceptions\AuthenticateException;

abstract class Controller extends BaseController
{

	use DispatchesCommands, ValidatesRequests;

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var Request
	 */
	protected $response;

	/**
	 * @var Session
	 */
	protected $session;

	/**
	 * @var bool
	 */
	public $authRequired = FALSE;

	/**
	 * @var array
	 */
	public $allowedActions = [];

	/**
	 * @var array
	 */
	protected $permissions = [];


	/**
	 * @param Request $request
	 * @param Response $response
	 * return void
	 */
	public function __construct(Request $request, Response $response, SessionStore $session)
	{
		$this->request = $request;
		$this->response = $response;
		$this->session = $session;

		// Execute method boot() on controller execute
		if (method_exists($this, 'boot')) {
			app()->call([$this, 'boot']);
		}

		if ($this->authRequired) {
			$this->beforeFilter('@checkPermissions', ['except' => $this->allowedActions]);
		}
	}

	/**
	 * Execute before an action executed
	 * return void
	 */
	public function before(){}

	/**
	 * Execute after an action executed
	 * return void
	 */
	public function after(){}

	/**
	 * @param string $separator
	 * @return string
	 */
	public function getRouterPath($separator = '.')
	{
		$controller = $this->getRouter()->currentRouteAction();
		$namespace = array_get($this->getRouter()->getCurrentRoute()->getAction(), 'namespace');
		$path = trim(str_replace($namespace, '', $controller), '\\');

		return str_replace(['\\', '@', '..', '.controller.'], $separator, Str::snake($path, '.'));
	}

	/**
	 * @return string
	 */
	public function getRouterController()
	{
		return last(explode('\\', get_called_class()));
	}

	/**
	 * @return string
	 */
	public function getCurrentAction()
	{
		list($class, $method) = explode('@', $this->getRouter()->currentRouteAction());

		return $method;
	}

	/**
	 * Execute an action on the controller.
	 *
	 * @param  string $method
	 * @param  array $parameters
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function callAction($method, $parameters)
	{
		$this->before();

		$response = call_user_func_array([$this, $method], $parameters);

		$this->after($response);

		return $response;
	}

	/**
	 * @param Route $router
	 * @param Request $request
	 * @return Response
	 */
	public function checkPermissions(Route $router, $request)
	{
		if (auth()->guest()) {
			return $this->denyAccess(trans('cms::core.messages.deny_access'), TRUE);
		}

		if (!acl_check(array_get($this->permissions, $this->getCurrentAction()))) {
			return $this->denyAccess(trans('cms::core.messages.no_permissions'));
		}
	}

	/**
	 * @param string|array|null $message
	 * @param bool $redirect
	 * @return Response
	 */
	public function denyAccess($message = NULL, $redirect = FALSE)
	{
		if ($this->request->ajax()) {
			throw new AuthenticateException('Unauthorized.');
		} elseif ($redirect) {
			return redirect()
				->guest(\CMS::backendPath() . '/auth/login')
				->withErrors($message);
		} else {
			abort(403, $message);
		}
	}
}
