<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{config('app.name')}}</title>
    <link rel="icon" href="{{ config('app.logo_url') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ config('app.logo_url') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Alpine.js -->
    @yield('css_js_head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if(config('services.google.maps_api_key'))
    <script>
        (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]);for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
            key: "{{ config('services.google.maps_api_key') }}",
            v: "weekly"
        });
    </script>
    @endif

    <!-- Restore the saved sidebar state before first paint to avoid a flash of the wrong width -->
    <script>
        try {
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                document.documentElement.classList.add('sidebar-collapsed-init');
            }
        } catch (e) {}
    </script>

    <style>
        [x-cloak] { display: none !important; }
        .sidebar-collapsed {
            width: 70px;
        }
        .sidebar-expanded {
            width: 260px;
        }
        @media (max-width: 1024px) {
            .sidebar-collapsed, .sidebar-expanded {
                width: 260px;
            }
        }

        /* Pre-Alpine paint only: removed by x-init as soon as Alpine takes over */
        @media (min-width: 1024px) {
            html.sidebar-collapsed-init .app-sidebar {
                width: 70px;
            }
            html.sidebar-collapsed-init .app-content {
                margin-left: 70px;
            }
        }

        /* Prevent layout shift when modal opens by always reserving scrollbar space */
        html {
            overflow-y: scroll;
        }

        /* When body has overflow-hidden (modal open), prevent scroll but keep scrollbar space */
        body.overflow-hidden {
            overflow: hidden;
            padding-right: 0 !important; /* Override any JS padding */
        }
    </style>
</head>
