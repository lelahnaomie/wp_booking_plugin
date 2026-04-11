/* Smart Booking v4.4 — All UX improvements */
(function($){'use strict';

window.SBApp={
    state:{step:1,service:null,staff:null,date:null,time:null,calYear:new Date().getFullYear(),calMonth:new Date().getMonth()+1,unavailable:[],bookingResult:null},
    goTo:function(n){
        if(n<this.state.step||canGoTo(n)){
            this.state.step=n;
            $('.sb-panel').hide().removeClass('active');$('#sbStep'+n).fadeIn(260).addClass('active');
            updateBubbles(n);
            var w=$('.sb-wrap');if(w.length)$('html,body').animate({scrollTop:w.offset().top-40},300);
        }
    }
};

function canGoTo(n){var s=window.SBApp.state;if(n<=1)return true;if(n===2)return !!s.service&&!!s.staff;if(n===3)return !!s.service&&!!s.staff&&!!s.date&&!!s.time;if(n===4)return !!s.service&&!!s.staff&&!!s.date&&!!s.time;return false;}
function updateBubbles(cur){$('.sb-step-bubble').each(function(){var s=parseInt($(this).data('step'));$(this).removeClass('active done');if(s===cur)$(this).addClass('active');else if(s<cur)$(this).addClass('done');});}
var currency=$('[data-currency]').attr('data-currency')||'FCFA';

$(document).ready(function(){setupCalNav();setupTabs();setupPolicy();window.SBApp.goTo(1);});

/* STEP TABS */
function setupTabs(){$(document).on('click','.sb-step-bubble.done',function(){var n=parseInt($(this).data('step'));if(n&&n<window.SBApp.state.step)window.SBApp.goTo(n);});}

/* POLICY MODAL */
function setupPolicy(){
    $(document).on('click','#sbOpenPolicy',function(e){e.preventDefault();$('#sbPolicyModal').fadeIn(200);});
    $(document).on('click','#sbClosePolicy',function(){$('#sbPolicyModal').fadeOut(150);});
    $(document).on('click','.sb-modal-overlay',function(e){if(e.target===this)$(this).fadeOut(150);});
    $(document).on('keydown',function(e){if(e.key==='Escape')$('#sbPolicyModal,#sbSvcModal').fadeOut(150);});
}

/* STEP 1: SERVICE POPUP */
$(document).on('click','.sb-service-card',function(){openServiceModal($(this));});

function openServiceModal($c){
    var id=parseInt($c.data('id')),name=$c.data('name')||$c.find('strong').first().text().trim();
    var price=parseFloat($c.data('price'))||0,depPct=parseFloat($c.data('deposit'))||0;
    var dur=parseInt($c.data('duration'))||60,desc=$c.data('desc')||'';
    var color=$c.data('color')||'#5B2D8E',image=$c.data('image')||'';
    var staffList=[];try{staffList=JSON.parse($c.attr('data-staff')||'[]');}catch(e){}
    var dep=depPct>0?Math.round(price*depPct/100):0;

    var staffHtml='';
    if(staffList.length===0){
        staffHtml='<div class="sb-svc-no-staff">A staff member will be assigned to your appointment.</div>';
    } else if(staffList.length===1){
        var st=staffList[0];
        staffHtml='<div class="sb-svc-staff-single">'
            +(st.image?'<img src="'+esc(st.image)+'" class="sb-svc-staff-av" alt="">':'<span class="sb-svc-staff-av" style="background:'+esc(st.color||'#5B2D8E')+'">'+esc(st.name.charAt(0))+'</span>')
            +'<div><strong>'+esc(st.name)+'</strong>'+(st.bio?'<p>'+esc(st.bio)+'</p>':'')+'</div></div>';
    } else {
        staffHtml='<label class="sb-svc-staff-label">Choose a staff member</label>'
            +'<select class="sb-svc-staff-sel" id="sbModalStaffSel">'
            +'<option value="0" data-name="Any available staff" data-color="#5B2D8E">Staff</option>';
        staffList.forEach(function(st){
            staffHtml+='<option value="'+st.id+'" data-name="'+esc(st.name)+'" data-color="'+esc(st.color||'#5B2D8E')+'">'+esc(st.name)+(st.bio?' · '+esc(st.bio.substring(0,40)):'')+'</option>';
        });
        staffHtml+='</select><p id="sbStaffBioPrev" class="sb-staff-bio-preview"></p>';
    }

    var html='<div class="sb-svc-modal-inner">'
        +(image?'<div class="sb-svc-modal-img" style="background-image:url(\''+esc(image)+'\')"></div>':'<div class="sb-svc-modal-bar" style="background:'+esc(color)+'"></div>')
        +'<div class="sb-svc-modal-body">'
        +'<h3>'+esc(name)+'</h3>'
        +'<div class="sb-svc-modal-meta">'
        +'<span>⏱ '+fmtDur(dur)+'</span>'
        +(price>0?'<span class="sb-svc-modal-price">'+fmt(price)+' '+currency+'</span>':'<span>Free</span>')
        +(dep>0?'<span class="sb-svc-dep-badge">Deposit: '+fmt(dep)+' '+currency+'</span>':'')
        +'</div>'
        +(desc?'<p class="sb-svc-modal-desc">'+esc(desc)+'</p>':'')
        +'<div class="sb-svc-staff-section"><h4>Who will serve you?</h4>'+staffHtml+'</div>'
        +'</div>'
        +'<div class="sb-svc-modal-foot">'
        +'<button class="sb-btn sb-btn-ghost" id="sbSvcCancel">Cancel</button>'
        +'<button class="sb-btn sb-btn-primary" id="sbSvcSelect"'
        +' data-id="'+id+'" data-name="'+esc(name)+'" data-price="'+price+'" data-dep="'+depPct+'" data-dur="'+dur+'">Select &amp; Continue &#8594;</button>'
        +'</div></div>';

    if(!$('#sbSvcModal').length)$('<div class="sb-modal-overlay" id="sbSvcModal"></div>').appendTo('.sb-wrap');
    $('#sbSvcModal').html('<div class="sb-modal-box sb-svc-modal-box">'+html+'</div>').fadeIn(200);
    $('#sbSvcModal').data({staff:staffList,single:staffList.length===1?staffList[0]:null,svcId:id});

    $(document).off('change.bsel').on('change.bsel','#sbModalStaffSel',function(){
        var sid=parseInt($(this).val()),found=staffList.find(function(s){return s.id===sid;});
        $('#sbStaffBioPrev').text(found&&found.bio?found.bio:'');
    });
    $(document).off('click.svcx').on('click.svcx','#sbSvcCancel',function(){$('#sbSvcModal').fadeOut(150);});
    $(document).off('click.svcs').on('click.svcs','#sbSvcSelect',function(){
        var bid=$(this).data('id'),bn=$(this).data('name'),bp=parseFloat($(this).data('price'))||0;
        var bd=parseFloat($(this).data('dep'))||0,bdur=parseInt($(this).data('dur'))||60;
        var single=$('#sbSvcModal').data('single'),stl=$('#sbSvcModal').data('staff')||[];
        var sid=0,sname='Any available staff',scol='#5B2D8E';
        if(single){sid=single.id;sname=single.name;scol=single.color||'#5B2D8E';}
        else if(stl.length>0){var sel=$('#sbModalStaffSel');if(sel.length){sid=parseInt(sel.val())||0;sname=sel.find('option:selected').data('name')||'Any available staff';scol=sel.find('option:selected').data('color')||'#5B2D8E';}}
        window.SBApp.state.service={id:bid,name:bn,price:bp,depositPct:bd,duration:bdur};
        window.SBApp.state.staff={id:sid,name:sname,color:scol};
        $('.sb-service-card').removeClass('selected');$('.sb-service-card[data-id="'+bid+'"]').addClass('selected');
        $('#sbSvcModal').fadeOut(150);
        renderCalendar();window.SBApp.goTo(2);
    });
}

/* STEP 2: CALENDAR */
function setupCalNav(){$(document).on('click','#sbCalPrev',function(){adj(-1);});$(document).on('click','#sbCalNext',function(){adj(1);});}
function adj(o){window.SBApp.state.calMonth+=o;if(window.SBApp.state.calMonth<1){window.SBApp.state.calMonth=12;window.SBApp.state.calYear--;}if(window.SBApp.state.calMonth>12){window.SBApp.state.calMonth=1;window.SBApp.state.calYear++;}renderCalendar();}
function renderCalendar(){
    var y=window.SBApp.state.calYear,m=window.SBApp.state.calMonth;
    var mn=['January','February','March','April','May','June','July','August','September','October','November','December'];
    $('#sbCalMonth').text(mn[m-1]+' '+y);$('#sbCalGrid').html('<div class="sb-loading">Loading...</div>');$('#sbSlotsGrid').empty();$('#sbSlotsTitle').text('Select a date');
    $.get(SB.ajax,{action:'sb_get_unavailable',service_id:window.SBApp.state.service.id,staff_id:window.SBApp.state.staff.id,year:y,month:m},function(res){window.SBApp.state.unavailable=res.success?res.data:[];buildCal(y,m);}).fail(function(){window.SBApp.state.unavailable=[];buildCal(y,m);});
}
function buildCal(y,m){
    var today=new Date();today.setHours(0,0,0,0);var first=new Date(y,m-1,1).getDay(),days=new Date(y,m,0).getDate(),h='';
    ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function(d){h+='<div class="sb-cal-day-label">'+d+'</div>';});
    for(var i=0;i<first;i++)h+='<div class="sb-cal-day sb-cal-empty"></div>';
    for(var d=1;d<=days;d++){var ds=y+'-'+pad(m)+'-'+pad(d),dt=new Date(y,m-1,d),cls='sb-cal-day';if(dt<today||window.SBApp.state.unavailable.indexOf(ds)!==-1)cls+=' sb-cal-unavail';if(window.SBApp.state.date===ds)cls+=' sb-cal-selected';h+='<div class="'+cls+'" data-date="'+ds+'">'+d+'</div>';}
    $('#sbCalGrid').html(h);
}
$(document).on('click','.sb-cal-day:not(.sb-cal-unavail):not(.sb-cal-empty)',function(){
    window.SBApp.state.date=$(this).data('date');window.SBApp.state.time=null;
    $('.sb-cal-day').removeClass('sb-cal-selected');$(this).addClass('sb-cal-selected');fetchSlots();
});
function fetchSlots(){
    var dl=new Date(window.SBApp.state.date+'T00:00').toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long'});
    $('#sbSlotsTitle').text(dl);$('#sbSlotsGrid').html('<div class="sb-loading">Loading times...</div>');
    $.get(SB.ajax,{action:'sb_get_slots',service_id:window.SBApp.state.service.id,staff_id:window.SBApp.state.staff.id,date:window.SBApp.state.date},function(res){
        if(!res.success||!res.data.length){$('#sbSlotsGrid').html('<p class="sb-notice">No available times on this date.</p>');return;}
        var h='';$.each(res.data,function(i,sl){var cls=sl.available?'sb-slot':'sb-slot sb-slot-taken';h+='<div class="'+cls+'" data-time="'+sl.time+'" data-end="'+(sl.end_time||'')+'" data-label="'+sl.label+'">'+sl.label+'</div>';});$('#sbSlotsGrid').html(h);
    });
}
$(document).on('click','.sb-slot:not(.sb-slot-taken)',function(){
    $('.sb-slot').removeClass('selected');$(this).addClass('selected');
    window.SBApp.state.time={time:$(this).data('time'),end_time:$(this).data('end')||'',label:$(this).data('label')};
    setTimeout(function(){window.SBApp.goTo(3);},350);
});

/* STEP 3 → 4 REVIEW */
window.SBApp.goToConfirm=function(){
    if(!window.SBApp.state.time){showErr('Please select a date and time first.');window.SBApp.goTo(2);return;}
    var name=$('#sbCustName').val().trim(),phone=$('#sbCustPhone').val().trim();
    $('.sb-ferr').text('');$('input').removeClass('is-err');var ok=true;
    if(!name){$('#sbErrName').text('Name is required.');$('#sbCustName').addClass('is-err');ok=false;}
    if(!phone){$('#sbErrPhone').text('Phone is required.');$('#sbCustPhone').addClass('is-err');ok=false;}
    if(!ok)return;buildReview();window.SBApp.goTo(4);
};
function buildReview(){
    var s=window.SBApp.state,name=$('#sbCustName').val().trim(),phone=$('#sbCustPhone').val().trim(),email=$('#sbCustEmail').val().trim(),notes=$('#sbCustNotes').val().trim();
    var dl=new Date(s.date+'T00:00').toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
    var dep=s.service.depositPct>0?Math.round(s.service.price*s.service.depositPct/100):0,bal=s.service.price-dep;
    var h='<div class="sb-review-rows">'+rrow('Service',s.service.name)+rrow('Staff',s.staff.name)+rrow('Date',dl)+rrow('Time',s.time.label+(s.time.end_time?' — '+toAmPm(s.time.end_time):''))+rrow('Name',name)+rrow('Phone',phone)+(email?rrow('Email',email):'')+(notes?rrow('Notes',notes):'');
    if(s.service.price>0)h+='<div class="sb-review-row sb-rr-total"><span class="sb-rlabel">Total</span><span class="sb-rvalue sb-review-price">'+fmt(s.service.price)+' '+currency+'</span></div>';
    h+='</div>';
    if(dep>0)h+='<div class="sb-deposit-info-box"><div class="sb-dib-row"><span>Deposit ('+s.service.depositPct+'%)</span><strong>'+fmt(dep)+' '+currency+'</strong></div><div class="sb-dib-row sb-dib-bal"><span>Balance on appointment day</span><span>'+fmt(bal)+' '+currency+'</span></div><p class="sb-dib-note">We will contact you to arrange the deposit payment.</p></div>';
    $('#sbReviewCard').html(h);
}
function rrow(l,v){return '<div class="sb-review-row"><span class="sb-rlabel">'+esc(l)+'</span><span class="sb-rvalue">'+esc(v)+'</span></div>';}

/* SUBMIT */
window.SBApp.submitBooking=function(){
    var $btn=$('#sbConfirmBtn');$btn.prop('disabled',true).find('.sb-btn-label').hide();$btn.find('.sb-btn-spinner').show();hideErr();
    $.post(SB.ajax,{action:'sb_submit_booking',nonce:window.SB_NONCE||(typeof SB!=='undefined'&&SB.nonce?SB.nonce:''),
        service_id:window.SBApp.state.service.id,staff_id:window.SBApp.state.staff.id,date:window.SBApp.state.date,
        time:window.SBApp.state.time.time,end_time:window.SBApp.state.time.end_time||'',
        name:$('#sbCustName').val().trim(),phone:$('#sbCustPhone').val().trim(),email:$('#sbCustEmail').val().trim(),notes:$('#sbCustNotes').val().trim()
    },function(res){
        $btn.prop('disabled',false).find('.sb-btn-label').show();$btn.find('.sb-btn-spinner').hide();
        if(!res.success){showErr(res.data.msg||'Something went wrong.');return;}
        var d=res.data;window.SBApp.state.bookingResult=d;
        if(d.deposit_required&&d.deposit_amount>0)showPaymentStep(d);else showFinalSuccess(d);
    }).fail(function(){$btn.prop('disabled',false).find('.sb-btn-label').show();$btn.find('.sb-btn-spinner').hide();showErr('Connection error. Try again.');});
};

/* PAYMENT */
function showPaymentStep(d){
    var dep=d.deposit_amount,cur=d.currency||currency;
    var h='<div class="sb-payment-step"><div class="sb-pay-header"><div class="sb-pay-lock">🔒</div><h3>Pay Deposit to Confirm</h3><p>Booking <strong>#'+d.booking_id+'</strong> awaits deposit.</p><div class="sb-pay-amount-box"><span class="sb-pay-label">Deposit required</span><span class="sb-pay-amt">'+fmt(dep)+' '+cur+'</span></div></div>';
    if(d.paystack_pk)h+='<button class="sb-pay-btn sb-pay-card" id="sbPayPaystack">💳 Pay by Card / Bank (Paystack)</button>';
    if(d.flw_pk)h+='<button class="sb-pay-btn sb-pay-mtn" id="sbPayFlw">📱 MTN / Orange Money (Flutterwave)</button>';
    h+='</div>';
    if(!$('#sbPaymentPanel').length)$('<div class="sb-panel" id="sbPaymentPanel"></div>').appendTo('.sb-wrap');
    $('.sb-panel').hide().removeClass('active');$('#sbPaymentPanel').html(h).show().addClass('active');
    $(document).off('click.ps').on('click.ps','#sbPayPaystack',function(){typeof PaystackPop==='undefined'?loadScript('https://js.paystack.co/v1/inline.js',function(){launchPS(d);}):launchPS(d);});
    $(document).off('click.flw').on('click.flw','#sbPayFlw',function(){typeof FlutterwaveCheckout==='undefined'?loadScript('https://checkout.flutterwave.com/v3.js',function(){launchFLW(d);}):launchFLW(d);});
}
function launchPS(d){PaystackPop.setup({key:d.paystack_pk,email:d.customer_email||'noemail@smartbooking.cm',amount:Math.round(d.deposit_amount*100),currency:d.currency==='FCFA'?'GHS':d.currency||'GHS',ref:'SB-'+d.booking_id+'-'+Date.now(),callback:function(r){verifyPS(r.reference,d.booking_id);},onClose:function(){}}).openIframe();}
function verifyPS(ref,bid){$('#sbPaymentPanel').html('<div style="padding:40px;text-align:center;color:#5B2D8E">Verifying...</div>');$.post(SB.ajax,{action:'sb_paystack_verify',nonce:window.SB_NONCE||'',reference:ref,booking_id:bid},function(res){if(res.success)showFinalSuccess($.extend({},window.SBApp.state.bookingResult,res.data));else alert(res.data.msg||'Verification failed. Ref: '+ref);});}
function launchFLW(d){FlutterwaveCheckout({public_key:d.flw_pk,tx_ref:'SB-'+d.booking_id+'-'+Date.now(),amount:d.deposit_amount,currency:d.currency||'XOF',payment_options:'mobilemoneyfranco,mobilemoneyghana,card',customer:{email:d.customer_email||'noemail@smartbooking.cm',phone_number:d.customer_phone,name:d.customer_name},customizations:{title:'Deposit',description:'#'+d.booking_id},callback:function(data){if(data.status==='successful')verifyFLW(data.transaction_id,d.booking_id);},onclose:function(){}});}
function verifyFLW(txid,bid){$('#sbPaymentPanel').html('<div style="padding:40px;text-align:center;color:#5B2D8E">Verifying...</div>');$.post(SB.ajax,{action:'sb_flw_verify',nonce:window.SB_NONCE||'',transaction_id:txid,booking_id:bid},function(res){if(res.success)showFinalSuccess($.extend({},window.SBApp.state.bookingResult,res.data));else alert(res.data.msg||'Verification failed.');});}

/* SUCCESS */
function showFinalSuccess(d){
    $('#sbWaBtn').attr('href',d.wa_url||'#');$('#sbPdfBtn').attr('href',d.pdf_url||'#');
    var dep=d.deposit_amount||0,cur=d.currency||currency;
    var msg='Booking #'+d.booking_id+' received! ';
    if(dep>0&&!d.deposit_required)msg+='We will contact you to arrange the deposit of '+fmt(dep)+' '+cur+'.';
    else if(dep>0)msg+='Deposit confirmed. Your booking is locked in!';
    else msg+='We will confirm your appointment shortly.';
    $('#sbSuccessMsg').text(msg);
    if(d.appointment_rules)$('#sbSuccessRules').html('<div class="sb-rules-box"><h4>📋 Appointment Rules</h4><p>'+esc(d.appointment_rules)+'</p></div>');
    $('.sb-panel').hide().removeClass('active');$('#sbSuccess').fadeIn(350).addClass('active');
    $('.sb-step-bubble').addClass('done');
}

/* RESET */
window.SBApp.reset=function(){
    window.SBApp.state={step:1,service:null,staff:null,date:null,time:null,calYear:new Date().getFullYear(),calMonth:new Date().getMonth()+1,unavailable:[],bookingResult:null};
    $('#sbCustName,#sbCustPhone,#sbCustEmail,#sbCustNotes').val('');
    $('.sb-service-card,.sb-slot').removeClass('selected');$('#sbPaymentPanel').remove();
    $('.sb-panel').hide().removeClass('active');$('#sbStep1').show().addClass('active');
    $('.sb-step-bubble').removeClass('active done');$('.sb-step-bubble[data-step="1"]').addClass('active');
};

/* UTILS */
function showErr(m){$('#sbError').text(m).fadeIn(200);}
function hideErr(){$('#sbError').fadeOut(150).text('');}
function pad(n){return n<10?'0'+n:''+n;}
function fmt(n){return Math.round(n).toLocaleString();}
function esc(s){return $('<div>').text(String(s||'')).html();}
function toAmPm(t){if(!t)return '';var p=t.split(':'),h=parseInt(p[0]),m=p[1],ap=h>=12?'PM':'AM';h=h%12||12;return h+':'+m+' '+ap;}
function fmtDur(min){if(min<60)return min+' min';var h=Math.floor(min/60),m=min%60;return m?h+'h '+m+'min':h+'h';}
function loadScript(src,cb){var s=document.createElement('script');s.src=src;s.onload=cb;document.head.appendChild(s);}

})(jQuery);