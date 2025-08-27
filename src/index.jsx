
import './index.scss';

const clientData = {
    height:   window.screen.height,
    width:    window.screen.width,
    pixelRes: window.devicePixelRatio,
    tzOffset: new Date().getTimezoneOffset(),
};

document.getElementById( 'gs_support-widget__client' ).value = JSON.stringify( clientData );
