$(function (){
    var now = new Date();
    var ph = "Now";
    var val = "";
    var h = now.getHours();
    if(h < 9 || h >= 18){
	ph = "9:00";
	val = "9:00";
    }
    $("#input-clock1").attr("placeholder", ph);
    $("#input-clock1").attr("value", val);
});
