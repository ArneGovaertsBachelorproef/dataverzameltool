
// based on : https://stackoverflow.com/a/21712356

function isIE() {
    const ua        = window.navigator.userAgent;
    const msie      = ua.indexOf('MSIE '); // IE 10 or older
    const trident   = ua.indexOf('Trident/'); // IE 11
    const edge      = ua.indexOf('Edge/'); // old Edge

    return (msie > 0 || trident > 0 || edge > 0);
}

if(isIE()) {
    document.getElementById('ie').style.display = 'block';
}