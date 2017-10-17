<div id="messages"></div>

<script>
var conn = new WebSocket(`ws://${window.location.hostname}:9999`);
conn.onopen = function(e) {
    console.log("Connection established!");
};

conn.onmessage = function(e) {
    console.log(e.data);
};
</script>
