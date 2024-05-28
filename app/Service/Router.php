<?php
namespace Service;
use \Exception;

class Router
{
  protected $routes = [];
  protected $redirects = [];
  
  public function addRedirect(string $method, string $from, string $to) : null
  {
      $this->redirects[$method][$from] = $to;
      return null;
  }

  public function addRoute(string $method, string $url, $target) : null
  {
      $this->routes[$method][$url] = $target;
      return null;
  }

  public function matchRoute()
  {
    $method = $_SERVER['REQUEST_METHOD'];
    $url = $_SERVER['REQUEST_URI'];

    if (isset($this->routes[$method]))
    {
      foreach ($this->routes[$method] as $routeUrl => $target)
      {
        $pattern = preg_replace('/\/\{([^\/]+)\}/', '/(?P<$1>[^/]+)', $routeUrl);

        if (preg_match('#^' . $pattern . '$#', $url, $matches))
        {
          $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
          call_user_func_array($target, $params);
          return;
        }
      }

      foreach ($this->redirects[$method] as $from => $to)
      {
        if ($url === $from)
        {
          header('Location: '.$to);
          return;
        }
      }
    }

    throw new Exception('Route not found');
  }

}

?>
