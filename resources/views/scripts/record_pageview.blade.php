<script>

    function put(url, payload){

        const _token = '{{ csrf_token() }}';
        const body = JSON.stringify({ _token, ...payload });
        const headers = { 'Content-Type': 'application/json' };
        
        return fetch(url, { method: 'PUT', body, headers });
    }

    const pageview_id = {{ \MatheusFS\Laravel\Insights\Facade::recordPageview()->getKey() }};
    const pageview_start = new Date();
    const screen_width = window.innerWidth;
    const screen_height = window.innerHeight;

    put(`/insights/pageviews/${pageview_id}`, { pageview: { screen_width, screen_height } });

    window.onbeforeunload = e => {

        const seconds_spent = (new Date().getTime() - pageview_start.getTime()) / 1000;

        put(`/insights/pageviews/${pageview_id}`, { pageview: { seconds_spent } });
    }
</script>