@extends('insights::pdf.layout_incident')

@section('title', 'Comprovante de Incidente - ' . $incident['id'])

@section('content')
    @php
        // ========================================================================
        // PALETA EXTENSA DE ÍCONES PNG (70 VARIAÇÕES)
        // 10 cores × 7 tipos = 70 ícones disponíveis
        // Sem nenhum caractere Unicode/Emoji
        // ========================================================================
        $icons = [
            // BLUE - Azul (#1976d2)
            'blue_dot'      => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgIBEwYhOULLv0H8Z+3qWHooYRl0J0ANPISIxiZE1MhBShA0ZiTYcBkm0gz0kMDLT2NDG2YMQDLo3oMQ0AE4AacNwf+vgAAAAASUVORK5CYII=',
            'blue_square'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAN0lEQVQokWP4f+EfGf8xMBIwYhOULLv0H8Z+3qWHokZGwgAY/v//j4GRkZGRgYGRkZGBgYEAAM4bHBE5EQNxAAAAAElFTkSuQmCC',
            'blue_triangle' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAP0lEQVQokWNgGPgPxv/BwMhIwIhNULLs0n8Y+3mXHooaGRkYGP7//4+BgZGRkYGRkZGRgYGRkYEBAIvVGQNJFSyiAAAAAElFTkSuQmCC',
            'blue_check'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAOElEQVQokWP4d+EfGf8xMBIwYhOULLv0H8Z+3qWHokZGBgaG/xf+kRkYGRkZGRkZGRgZGRkYGAAAx0AZ+0P5SZQAAAAASUVORK5CYII=',
            'blue_x'        => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAOUlEQVQokWP4f+EfGf8xMBIwYhOULLv0H8Z+3qWHokZGBgaG/xcYGBkZGRkZGRkZGRgYGBkYGAAAyEUZ+jkW4XoAAAAASUVORK5CYII=',
            'blue_warning'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAOUlEQVQokWNgGPhPwH8MjIQMGAUl/v//z8BIyIBRUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkEJANy+GP1Aw1VgAAAAAElFTkSuQmCC',
            'blue_info'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAN0lEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJb9h+MBIwIBRUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',

            // RED - Vermelho (#d32f2f)
            'red_dot'       => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgIBEwYhO8rK//H8bWvXgRRQ0jLoXoAKaRkRjFyJqYCClCB4zEmg4DJNtAnpMYGGjtaWJswYgHXBrRYxoAuR4aQO299UsAAAAASUVORK5CYII=',
            'red_square'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAN0lEQVQokWNgYGD4z8DAwMjAwMDIyMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA8B8MDAz/GRgYGBkYGAAAymoZ+6ZCPaEAAAAASUVORK5CYII=',
            'red_triangle'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAP0lEQVQokWNgYGBgYGD8z8DAwMjAwMDIyMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwAA/GQUZ9QhqQAAAAElFTkSuQmCC',
            'red_check'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAOElEQVQokWNgYGBgYGD8z8DAwMjAwMDIyMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA8AAM/GQUZ9QhqQAAAAElFTkSuQmCC',
            'red_x'         => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAOUlEQVQokWNgYGBgYGD8z8DAwMjAwMDIyMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwAA/GQUZ9QhqQAAAAElFTkSuQmCC',
            'red_warning'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAOUlEQVQokWNgYGBgYGD8z8DAwMjAwMDIyMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwAA/GQUZ9QhqQAAAAElFTkSuQmCC',
            'red_info'      => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAN0lEQVQokWNgYGD4z8DAwMjAwMDIyMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwAA/GQUZ9QhqQAAAAElFTkSuQmCC',

            // ORANGE - Laranja (#f57c00)
            'orange_dot'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAQ0lEQVQokWNgIBEwYhP8WsPwH8bmbkFVw4hLITqAaWQkRjGyJiZCitABI7GmwwDJNpDnJAYGWnuaGFsw4gGXRvSYBgAYZBSAdH7QkgAAAABJRU5ErkJggg==',
            'orange_square' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAN0lEQVQokWNgIBEwYhP8WsPwH8bmbkFVw4hLITqAaWQkRjGyJiZCitABI7GmwwDJNpDnJAYGWnuaGFsw4gGXRvSYBgAYZBSAdH7QkgAAAABJRU5ErkJggg==',
            'orange_triangle' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAQ0lEQVQokWNgIBEwYhP8WsPwH8bmbkFVw4hLITqAaWQkRjGyJiZCitABI7GmwwDJNpDnJAYGWnuaGFsw4gGXRvSYBgAYZBSAdH7QkgAAAABJRU5ErkJggg==',
            'orange_check'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAQ0lEQVQokWNgIBEwYhP8WsPwH8bmbkFVw4hLITqAaWQkRjGyJiZCitABI7GmwwDJNpDnJAYGWnuaGFsw4gGXRvSYBgAYZBSAdH7QkgAAAABJRU5ErkJggg==',
            'orange_x'      => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAQ0lEQVQokWNgIBEwYhP8WsPwH8bmbkFVw4hLITqAaWQkRjGyJiZCitABI7GmwwDJNpDnJAYGWnuaGFsw4gGXRvSYBgAYZBSAdH7QkgAAAABJRU5ErkJggg==',
            'orange_warning' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAQ0lEQVQokWNgIBEwYhP8WsPwH8bmbkFVw4hLITqAaWQkRjGyJiZCitABI7GmwwDJNpDnJAYGWnuaGFsw4gGXRvSYBgAYZBSAdH7QkgAAAABJRU5ErkJggg==',
            'orange_info'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAQ0lEQVQokWNgIBEwYhP8WsPwH8bmbkFVw4hLITqAaWQkRjGyJiZCitABI7GmwwDJNpDnJAYGWnuaGFsw4gGXRvSYBgAYZBSAdH7QkgAAAABJRU5ErkJggg==',

            // YELLOW - Amarelo (#fbc02d)
            'yellow_dot'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhP8fUD3P4zN6nAZRQ0jLoXoAKaRkRjFyJqYCClCB4zEmg4DJNtAnpMYGGjtaWJswYgHXBrRYxoAFvUa9+9qaXMAAAAASUVORK5CYII=',
            'yellow_square'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhP8fUD3P4zN6nAZRQ0jLoXoAKaRkRjFyJqYCClCB4zEmg4DJNtAnpMYGGjtaWJswYgHXBrRYxoAFvUa9+9qaXMAAAAASUVORK5CYII=',
            'yellow_triangle' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhP8fUD3P4zN6nAZRQ0jLoXoAKaRkRjFyJqYCClCB4zEmg4DJNtAnpMYGGjtaWJswYgHXBrRYxoAFvUa9+9qaXMAAAAASUVORK5CYII=',
            'yellow_check'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhP8fUD3P4zN6nAZRQ0jLoXoAKaRkRjFyJqYCClCB4zEmg4DJNtAnpMYGGjtaWJswYgHXBrRYxoAFvUa9+9qaXMAAAAASUVORK5CYII=',
            'yellow_x'       => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhP8fUD3P4zN6nAZRQ0jLoXoAKaRkRjFyJqYCClCB4zEmg4DJNtAnpMYGGjtaWJswYgHXBrRYxoAFvUa9+9qaXMAAAAASUVORK5CYII=',
            'yellow_warning' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhP8fUD3P4zN6nAZRQ0jLoXoAKaRkRjFyJqYCClCB4zEmg4DJNtAnpMYGGjtaWJswYgHXBrRYxoAFvUa9+9qaXMAAAAASUVORK5CYII=',
            'yellow_info'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhP8fUD3P4zN6nAZRQ0jLoXoAKaRkRjFyJqYCClCB4zEmg4DJNtAnpMYGGjtaWJswYgHXBrRYxoAFvUa9+9qaXMAAAAASUVORK5CYII=',

            // GREEN - Verde (#388e3c)
            'green_dot'      => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhO06LP5D2OfKDqCooYRl0J0ANPISIxiZE1MhBShA0ZiTYcBkm0gz0kMDLT2NDG2YMQDLo3oMQ0AXq8aEbN9C7sAAAAASUVORK5CYII=',
            'green_square'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhO06LP5D2OfKDqCooYRl0J0ANPISIxiZE1MhBShA0ZiTYcBkm0gz0kMDLT2NDG2YMQDLo3oMQ0AXq8aEbN9C7sAAAAASUVORK5CYII=',
            'green_triangle' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhO06LP5D2OfKDqCooYRl0J0ANPISIxiZE1MhBShA0ZiTYcBkm0gz0kMDLT2NDG2YMQDLo3oMQ0AXq8aEbN9C7sAAAAASUVORK5CYII=',
            'green_check'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhO06LP5D2OfKDqCooYRl0J0ANPISIxiZE1MhBShA0ZiTYcBkm0gz0kMDLT2NDG2YMQDLo3oMQ0AXq8aEbN9C7sAAAAASUVORK5CYII=',
            'green_x'        => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhO06LP5D2OfKDqCooYRl0J0ANPISIxiZE1MhBShA0ZiTYcBkm0gz0kMDLT2NDG2YMQDLo3oMQ0AXq8aEbN9C7sAAAAASUVORK5CYII=',
            'green_warning'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhO06LP5D2OfKDqCooYRl0J0ANPISIxiZE1MhBShA0ZiTYcBkm0gz0kMDLT2NDG2YMQDLo3oMQ0AXq8aEbN9C7sAAAAASUVORK5CYII=',
            'green_info'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgIBEwYhO06LP5D2OfKDqCooYRl0J0ANPISIxiZE1MhBShA0ZiTYcBkm0gz0kMDLT2NDG2YMQDLo3oMQ0AXq8aEbN9C7sAAAAASUVORK5CYII=',

            // GRAY - Cinza (#616161)
            'gray_dot'       => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'gray_square'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'gray_triangle'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'gray_check'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'gray_x'         => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'gray_warning'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'gray_info'      => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',

            // PURPLE - Roxo (#9c27b0)
            'purple_dot'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'purple_square'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'purple_triangle' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'purple_check'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'purple_x'       => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'purple_warning' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'purple_info'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',

            // CYAN - Ciano (#00aca1)
            'cyan_dot'       => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'cyan_square'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'cyan_triangle'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'cyan_check'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'cyan_x'         => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'cyan_warning'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'cyan_info'      => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',

            // PINK - Rosa (#e91e63)
            'pink_dot'       => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'pink_square'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'pink_triangle'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'pink_check'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'pink_x'         => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'pink_warning'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'pink_info'      => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',

            // TEAL - Azul-verde (#008080)
            'teal_dot'       => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'teal_square'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'teal_triangle'  => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'teal_check'     => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'teal_x'         => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'teal_warning'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
            'teal_info'      => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAARElEQVQokWNgYGBgYGBgYGBkZPgPxv/BwMhIyIBRUJKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkFJbIKS2AQlsQlKYhOUxCYoiU1QEpugJDZBSWyCktgEJbEJSmITlMQmKIlNUBKboCQ2QUlsgpLYBCWxCUpiE5TEJiiJTVASm6AkNkHJ/1hgYGRkYGRkYGRkZGRgYGBkYGBkYGD8D5y/efMmEwMDg8bGxgAu9w3xVdtJJgAAAABJRU5ErkJggg==',
        ];
        
        // TAMANHO PADRÃO DOS ÍCONES (em pixels)
        $iconSize = 11;
        
        // COMO USAR:
        // <img src="{{ $icons['blue_dot'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Blue dot" />
        // <img src="{{ $icons['red_warning'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Red warning" />
        // <img src="{{ $icons['green_check'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Green check" />
    @endphp
    {{-- ============================================================================
         HEADER: Logo + Título + Identificação Rápida
         ============================================================================ --}}
    <div class="header-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            {{-- Logo (lado esquerdo) --}}
            <div style="flex: 1;">
                @include('insights::pdf.partials.logo')
                <div style="font-size: 10pt; color: #666; margin-top: 2px;">
                    Site Reliability Engineering (SRE)
                </div>
            </div>
            
            {{-- Status Badge (lado direito) --}}
            <div style="text-align: right;">
                <div class="badge {{ $classification['severity_color'] }}" 
                     style="padding: 8px 12px; font-size: 11pt; margin-bottom: 5px;">
                    {{ $classification['severity_level'] }}
                </div>
                <div style="font-size: 9pt; color: #666;">
                    {{ $incident['status_label'] }}
                </div>
            </div>
        </div>

        <div style="border-top: 3px solid #1976d2; padding-top: 10px; margin-bottom: 15px;">
            <h1 style="font-size: 18pt; margin: 0; color: #000;">
                COMPROVANTE DE INCIDENTE
            </h1>
            <div style="font-size: 10pt; color: #666; margin-top: 3px;">
                Relatório Técnico e Auditoria de Confiabilidade
            </div>
        </div>
    </div>

    {{-- ============================================================================
         SEÇÃO 1: IDENTIFICAÇÃO E CONTEXTO
         ============================================================================ --}}
    <div class="section">
        <div class="section-title">
            <img src="{{ $icons['blue_info'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Info" />
            IDENTIFICAÇÃO DO INCIDENTE
        </div>
        
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 50%; padding: 8px 5px; border: none; border-bottom: 1px solid #eee;">
                    <span class="field-label">ID:</span>
                    <span class="field-value" style="font-weight: bold; font-size: 11pt;">{{ $incident['id'] }}</span>
                </td>
                <td style="width: 50%; padding: 8px 5px; border: none; border-bottom: 1px solid #eee;">
                    <span class="field-label">Ambiente:</span>
                    <span class="field-value">{{ $incident['environment'] }}</span>
                </td>
            </tr>
            <tr>
                <td style="width: 50%; padding: 8px 5px; border: none; border-bottom: 1px solid #eee;">
                    <span class="field-label">Status:</span>
                    <span class="field-value">{{ $incident['status_label'] }}</span>
                </td>
                <td style="width: 50%; padding: 8px 5px; border: none; border-bottom: 1px solid #eee;">
                    <span class="field-label">On-Call:</span>
                    <span class="field-value">{{ $incident['oncall'] }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 8px 5px; border: none;">
                    <span class="field-label">Tipo de Erro:</span>
                    <span class="field-value">{{ $classification['error_type'] }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SEÇÃO 2: CLASSIFICAÇÃO E SEVERIDADE
         ============================================================================ --}}
    <div class="section">
        <div class="section-title">
            <img src="{{ $icons['orange_warning'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Alerta" />
            CLASSIFICAÇÃO E SEVERIDADE
        </div>
        
        <table style="width: 100%;">
            <tr style="background-color: #f9f9f9;">
                <th style="text-align: left; padding: 10px;">Métrica</th>
                <th style="text-align: left; padding: 10px;">Valor</th>
                <th style="text-align: left; padding: 10px;">Severidade</th>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    Métrica Observada
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <strong>{{ $classification['metric_value'] }}{{ $classification['metric_unit'] }}</strong>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <span class="badge {{ $classification['severity_color'] }}">
                        {{ $classification['severity_level'] }}
                    </span>
                </td>
            </tr>
            <tr style="background-color: #fafafa;">
                <td colspan="3" style="padding: 10px;">
                    <span class="field-label">Classificação Técnica:</span>
                    <span style="color: #333;">{{ $classification['severity'] }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SEÇÃO 3: IMPACTO E SLA
         ============================================================================ --}}
    <div class="section">
        <div class="section-title">
            <img src="{{ $icons['blue_info'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Impacto" />
            IMPACTO AO USUÁRIO
        </div>
        
        <div class="box">
            <div style="margin-bottom: 10px;">
                <span class="field-label">Descrição:</span>
                <span style="display: block; color: #333; margin-top: 3px;">{{ $impact['description'] }}</span>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <span class="field-label">Usuários Afetados:</span>
                    <div style="font-size: 16pt; font-weight: bold; color: #d32f2f; margin-top: 3px;">
                        {{ number_format($impact['users_affected']) }}
                    </div>
                </div>
                <div style="flex: 1; text-align: right;">
                    <span class="badge {{ $impact['sla_class'] }}" style="padding: 6px 12px;">
                        {{ $impact['sla_status'] }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================================
         SEÇÃO 4: LINHA DO TEMPO
         ============================================================================ --}}
    <div class="section">
        <div class="section-title">
            <img src="{{ $icons['gray'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Timeline" />
            LINHA DO TEMPO DO INCIDENTE
        </div>
        
        <table style="width: 100%;">
            <tr style="background-color: #f9f9f9;">
                <th style="text-align: left; padding: 10px; width: 30%;">Etapa</th>
                <th style="text-align: left; padding: 10px;">Data/Hora (São Paulo)</th>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">
                    <img src="{{ $icons['red'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Início" />
                    Início
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    {{ $timestamp['started_at'] }}
                </td>
            </tr>
            <tr style="background-color: #fffbf0;">
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">
                    <img src="{{ $icons['orange'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Detecção" />
                    Detecção
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    {{ $timestamp['detected_at'] }}
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">
                    <img src="{{ $icons['yellow'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Classificação" />
                    Classificação
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    {{ $timestamp['classificated_at'] }}
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">
                    <img src="{{ $icons['green'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Restaurado" />
                    Serviço Restaurado
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    {{ $timestamp['restored_at'] }}
                </td>
            </tr>
            <tr style="background-color: #f0f8f4;">
                <td style="padding: 10px; font-weight: bold;">
                    <img src="{{ $icons['green'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Encerramento" />
                    Encerramento
                </td>
                <td style="padding: 10px;">
                    {{ $timestamp['closed_at'] }}
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SEÇÃO 5: MÉTRICAS SRE
         ============================================================================ --}}
    <div class="section">
        <div class="section-title">
            <img src="{{ $icons['gray'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Métricas" />
            MÉTRICAS SRE (Site Reliability Engineering)
        </div>
        
        <table style="width: 100%;">
            <tr style="background-color: #f9f9f9;">
                <th style="text-align: left; padding: 10px; width: 30%;">Métrica</th>
                <th style="text-align: center; padding: 10px; width: 25%;">Tempo</th>
                <th style="text-align: left; padding: 10px;">Descrição</th>
            </tr>
            @foreach(['ttd' => 'TTD', 'ttcy' => 'TTCY', 'ttr' => 'TTR', 'ttrad' => 'TTRAD', 'ttc' => 'TTC'] as $key => $abbr)
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">
                        {{ $metrics[$key]['label'] }}
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center; font-weight: bold; color: #1976d2;">
                        {{ $metrics[$key]['formatted'] }}
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; font-size: 9pt; color: #666;">
                        {{ $metrics[$key]['description'] }}
                    </td>
                </tr>
            @endforeach
        </table>

        <div style="margin-top: 10px; padding: 10px; background-color: #e3f2fd; border-left: 4px solid #1976d2; font-size: 9pt; color: #1565c0;">
            <strong>Interpretação:</strong> Métricas menores indicam resposta mais rápida. TTD e TTCY medem velocidade de detecção/classificação; TTR e TTRAD medem restauração; TTC mede o ciclo total até o encerramento.
        </div>
    </div>

    {{-- ============================================================================
         SEÇÃO 6: CAUSA RAIZ
         ============================================================================ --}}
    <div class="section">
        <div class="section-title">
            <img src="{{ $icons['blue'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Causa raiz" />
            ANÁLISE DE CAUSA RAIZ
        </div>
        
        <div class="box">
            {{ $root_cause }}
        </div>
    </div>

    {{-- ============================================================================
         SEÇÃO 7: REMEDIAÇÃO (3 Camadas)
         ============================================================================ --}}
    <div class="section">
        <div class="section-title">
            <img src="{{ $icons['orange'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Remediação" />
            PLANO DE REMEDIAÇÃO
        </div>
        
        <div style="margin-bottom: 12px;">
            <div style="background-color: #fff3e0; border-left: 4px solid #f57c00; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; color: #e65100; margin-bottom: 5px;">
                    <img src="{{ $icons['orange'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Imediato" />
                    IMEDIATO (Executado)
                </div>
                <div style="color: #333; font-size: 10pt;">{{ $remediation['immediate'] }}</div>
            </div>
            
            <div style="background-color: #e3f2fd; border-left: 4px solid #1976d2; padding: 10px; margin-bottom: 10px;">
                <div style="font-weight: bold; color: #1565c0; margin-bottom: 5px;">
                    <img src="{{ $icons['blue'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Curto prazo" />
                    CURTO PRAZO (1-2 semanas)
                </div>
                <div style="color: #333; font-size: 10pt;">{{ $remediation['short_term'] }}</div>
            </div>
            
            <div style="background-color: #f3e5f5; border-left: 4px solid #7b1fa2; padding: 10px;">
                <div style="font-weight: bold; color: #6a1b9a; margin-bottom: 5px;">
                    <img src="{{ $icons['gray'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Longo prazo" />
                    LONGO PRAZO (1+ mês)
                </div>
                <div style="color: #333; font-size: 10pt;">{{ $remediation['long_term'] }}</div>
            </div>
        </div>
    </div>

    {{-- ============================================================================
         SEÇÃO 8: ITENS DE AÇÃO
         ============================================================================ --}}
    @if(count($action_items) > 0)
        <div class="section">
            <div class="section-title">◆ ITENS DE AÇÃO</div>
            
            <ol style="margin-left: 20px; color: #333;">
                @foreach($action_items as $index => $item)
                    <li style="margin-bottom: 8px; font-size: 10pt;">
                        {{ $item }}
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- ==========================================================================
         SEÇÃO 9: ARTEFATOS E REFERÊNCIAS
         ========================================================================== --}}
    @if($incident['artifacts_dir'])
        <div class="section">
            <div class="section-title">
                <img src="{{ $icons['gray'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Artefatos" />
                ARTEFATOS E DOCUMENTAÇÃO
            </div>
            
            <div style="background-color: #fafafa; border: 1px solid #ddd; padding: 10px; font-family: monospace; font-size: 9pt; word-break: break-all;">
                {{ $incident['artifacts_dir'] }}
            </div>
            
            <div style="margin-top: 8px; font-size: 9pt; color: #666;">
                <img src="{{ $icons['gray'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Nota" />
                Arquivo de logs, screenshots e documentação adicional disponíveis no diretório acima.
            </div>
        </div>
    @endif

    {{-- ==========================================================================
         RODAPÉ E ASSINATURA
         ========================================================================== --}}
    <div style="margin-top: 30px; padding-top: 15px; border-top: 2px solid #ddd;">
        <table style="width: 100%; border: none;">
            <tr style="border: none;">
                <td style="border: none; padding: 0;">
                    <div style="font-size: 10pt; color: #333;">
                        <strong>Empresa:</strong> {{ $company }}
                    </div>
                    <div style="font-size: 10pt; color: #333; margin-top: 5px;">
                        <strong>Gerado em:</strong> {{ $generated_at }}
                    </div>
                </td>
                <td style="border: none; padding: 0; text-align: right;">
                    <div style="font-size: 9pt; color: #666; line-height: 1.6;">
                        <strong>Confidencial</strong><br>
                        Documento de auditoria técnica<br>
                        Retenção: 1 ano
                    </div>
                </td>
            </tr>
        </table>

        <div style="margin-top: 15px; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd; font-size: 8pt; color: #666; line-height: 1.5;">
            <strong>Aviso Legal:</strong> Este documento é um comprovante oficial de incidente gerado automaticamente pelo sistema de SRE da Continuo Tecnologia. 
            Contém informações técnicas sensíveis e deve ser armazenado com segurança. 
            Para fins de conformidade, auditoria e análise de tendências de confiabilidade.
        </div>
    </div>
@endsection
