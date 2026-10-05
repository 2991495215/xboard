<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" type="image/png" href="/theme/Xboard/images/txz.png?v=0.4.1">
    <title>{{$title}}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        @media (min-width: 769px) {
            html, body, div, main, section, article, aside, nav {
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }

            ::-webkit-scrollbar, ::-webkit-scrollbar-button, ::-webkit-scrollbar-track, ::-webkit-scrollbar-track-piece, ::-webkit-scrollbar-thumb, ::-webkit-scrollbar-corner, ::-webkit-resizer {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                background: transparent !important;
            }
        }

        html, body {
            width: 100%;
            height: 100%;
        }

        @media (min-width: 769px) {
            * {
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }

        #app {
            width: 100%;
            height: 100%;
        }

        html.preloader-active, body.preloader-active {
            overflow: hidden !important;
        }
    </style>
    <style>
        .app-version {
            position: fixed;
            left: 6px;
            bottom: 4px;
            font-size: 9px;
            color: rgba(0, 0, 0, 0.35);
            user-select: none;
            pointer-events: none;
            z-index: 5;
        }
    </style>
  <script>window.XBOARD_APP_NAME = @json($title);</script>
  <script type="module" crossorigin src="/theme/Xboard/static/index-BACKENDNAME-source-v1.js"></script>
  <link rel="modulepreload" crossorigin href="/theme/Xboard/static/vendor-QzbdZ9at.js">
  <link rel="stylesheet" crossorigin href="/theme/Xboard/static/vendor-BgDCIyLK.css">
</head>
<body>
<div id="app"></div>
<div class="app-version">v0.4.0</div>
</body>
</html>

