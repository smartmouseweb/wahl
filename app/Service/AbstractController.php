<?php
namespace Service;

class AbstractController
{
  public static $twig;

  public static function render(string $temnplate, $param) : string
  {
    $loader = new \Twig\Loader\FilesystemLoader('./app/View');
    self::$twig = new \Twig\Environment($loader);    

    return self::$twig->render($temnplate, $param);
  }
}
?>
