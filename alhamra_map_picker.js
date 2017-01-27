//------------------------------------------------------------------------------------------
function map_picker_init_map() {
//------------------------------------------------------------------------------------------
    map.options.maxZoom = 18;
    if(!curLayer)
        map.fitBounds([[15.78, 50.33], [26.75, 63.21]]);
    var baseMap = L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
}

//------------------------------------------------------------------------------------------
function map_picker_finish_init() {
//------------------------------------------------------------------------------------------
    if(curLayer) {
        map.fitBounds(L.featureGroup([curLayer]).getBounds());
        if(map.getZoom() > 10)
            map.setZoom(10);
    }
}
