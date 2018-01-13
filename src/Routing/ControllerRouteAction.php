<?php
namespace Concrete\Core\Routing;

use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Facade;
use Symfony\Component\HttpFoundation\Response;
use Concrete\Core\Http\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Concrete\Core\Controller\ApplicationAwareControllerResolver;
use Concrete\Core\View\AbstractView;

class ControllerRouteAction implements RouteActionInterface
{

    protected $action;

    /**
     * $action is something like \My\Controller::myAction
     * @param string $action
     */
    public function __construct($action)
    {
        $this->action = $action;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param Request $request
     * @param Route $route
     * @param array $parameters
     *
     * @return Response
     */
    public function execute(Request $request, Route $route, $parameters)
    {
        $request->attributes->set('_controller', $this->getAction());

        $app = Facade::getFacadeApplication();
        $controllerResolver = $app->make(ApplicationAwareControllerResolver::class);
        $callback = $controllerResolver->getController($request);
        $argumentsResolver = $app->make(ArgumentResolver::class);

        $arguments = $argumentsResolver->getArguments($request, $callback);
        $controller = $callback[0];
        $method = $callback[1];

        if (method_exists($controller, 'on_start')) {
            $controller->on_start();
        }

        if (method_exists($controller, 'runAction')) {
            $response = $controller->runAction($method, $arguments);
        } else {
            $response = call_user_func_array([$controller, $method], $arguments);
        }

        if ($response instanceof Response) {
            // note, our RedirectResponse doesn't extend Response, it extends symfony2 response
            return $response;
        }

        if ($response instanceof AbstractView) {
            $content = $response->render();
        } else if (method_exists($controller, 'getViewObject')) {
            $content = null;
            $view = $controller->getViewObject();
            if (is_object($view)) {
                $view->setController($controller);
                if (isset($view) && $view instanceof AbstractView) {
                    $content = $view->render();
                }
            }
        } else {
            $content = $response;
        }

        $response = new Response();
        $response->setContent($content);
        return $response;
    }

}