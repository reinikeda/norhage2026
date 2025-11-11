/* NH Price Summary — init block for CUSTOM-CUT SIMPLE products */
(function($){
  $(function(){
    if (!window.NHPriceSummary) return;
    var F = NHPriceSummary.fmt || (n => String(n));
    var rg = (window.NH_PS_INIT && +NH_PS_INIT.perm2_reg)  || 0;
    var sl = (window.NH_PS_INIT && +NH_PS_INIT.perm2_sale) || 0;
    var fee = (window.NH_PS_INIT && +NH_PS_INIT.cut_fee)   || 0;

    var perm2HTML =
      (rg>0 && sl>0 && sl<rg) ? ('<del>'+F(rg)+'</del> <ins>'+F(sl)+'</ins>') :
      (sl>0 ? ('<ins>'+F(sl)+'</ins>') :
      (rg>0 ? ('<ins>'+F(rg)+'</ins>') : '—'));

    var cutHTML = fee>0 ? F(fee) : '—';

    NHPriceSummary.update({ perm2_html: perm2HTML, cutfee_html: cutHTML, unit: '—', total: '—' });
    $('#nh-price-summary .nh-ps-perm2').css('display','flex');
    $('#nh-price-summary .nh-ps-cutfee').css('display', fee>0 ? 'flex' : '');
  });
})(jQuery);
