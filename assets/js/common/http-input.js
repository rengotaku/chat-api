window.HttpInput = {

    // _bar: 'メンバ変数',

    /**
     * GETパラメータを配列にセットして返却する。
     */
    getParams: function (){
        var url   = location.href;
        parameters    = url.split("?");
        params   = parameters[1].split("&");
        var paramsArray = [];
        for ( i = 0; i < params.length; i++ ) {
            neet = params[i].split("=");
            paramsArray.push(neet[0]);
            paramsArray[neet[0]] = neet[1];
        }
        return paramsArray;
    }
};
