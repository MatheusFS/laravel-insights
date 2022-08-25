@if(env('INSIGHTS_PAGEVIEW_ENABLED', true))

<script>
    function put(url, payload){

        const _token = '{{ csrf_token() }}';
        const body = JSON.stringify(Object.assign({ _token }, payload));
        const headers = { "Accept": "application/json", "Content-Type": "application/json" };
        
        return fetch(url, { method: 'PUT', body, headers });
    }
</script>

<script>

    const pageview_id = {{ \MatheusFS\Laravel\Insights\Facade::recordPageview()->getKey() }};
    const screen_width = window.innerWidth;
    const screen_height = window.innerHeight;
    
    const focus = e => { start_timespan = new Date() };

    const blur = e => { seconds_spent += new Date().getTime() - start_timespan.getTime(); };

    const beforeunload = e => {

        seconds_spent += new Date().getTime() - start_timespan.getTime();
        put(`/insights/pageviews/${pageview_id}`, { pageview: { seconds_spent: seconds_spent / 1000 } });
    };

    let start_timespan = new Date();
    let seconds_spent = 0;

    put(`/insights/pageviews/${pageview_id}`, { pageview: { screen_width, screen_height } });

    window.addEventListener('focus', focus);
    window.addEventListener('blur', blur);
    window.addEventListener('beforeunload', beforeunload);
</script>

@endif