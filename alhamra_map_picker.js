//------------------------------------------------------------------------------------------
function map_picker_init_map() {
//------------------------------------------------------------------------------------------
    map.options.maxZoom = 18;
    if(!curPointLayer)
        map.fitBounds([[15.78, 50.33], [26.75, 63.21]]);
    var baseMap = L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
}

//------------------------------------------------------------------------------------------
function map_picker_finish_init() {
//------------------------------------------------------------------------------------------
    if(curPointLayer) {
        map.setZoom(10);
        map.panTo(curPointLayer.getLatLng());
    }
}
