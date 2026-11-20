import fs from 'node:fs';
import path from 'node:path';

// Called only by the isolated synthetic test runtime, never shipped with plugin.
export async function runCvTests(cli, root) {
  const checks=[];
  const check=(name,actual,expected)=>{checks.push({name,pass:actual===expected,actual,expected});fs.writeFileSync(path.join(root,'cv-progress.json'),JSON.stringify({checks,total:checks.length,failed:checks.filter(c=>!c.pass).length},null,2));};
  const php=async(code)=>{const response=await cli.playground.run({code:`<?php require '/wordpress/wp-load.php'; ${code}`});if(response.exitCode!==0)throw Error(response.errors);try{return JSON.parse(response.text);}catch{throw Error(response.text+' '+response.errors);}};
  const command=fs.existsSync(path.join(root,'run-cv.signal'))?fs.readFileSync(path.join(root,'run-cv.signal'),'utf8').trim():'';
  const credentials=await php(`$u=get_user_by('login','admin'); wp_set_current_user($u->ID); $exp=time()+3600; $token=WP_Session_Tokens::get_instance($u->ID)->create($exp); $_COOKIE[LOGGED_IN_COOKIE]=wp_generate_auth_cookie($u->ID,$exp,'logged_in',$token); echo wp_json_encode(array('cookie'=>LOGGED_IN_COOKIE.'='.$_COOKIE[LOGGED_IN_COOKIE], 'nonce'=>wp_create_nonce('wp_rest')));`);
  const base='http://127.0.0.1:9441/wp-json/extraordinary-careers/v1';
  const httpFetch=(url,options={})=>{const headers={...options.headers};headers.Cookie='playground_auto_login_already_happened=1'+(headers.Cookie?'; '+headers.Cookie:'');return fetch(url,{...options,headers});};
  const req=async(route,options={},auth=false)=>{const headers={...(auth?{Cookie:credentials.cookie,'X-WP-Nonce':credentials.nonce}:{}),...options.headers};headers.Cookie='playground_auto_login_already_happened=1'+(headers.Cookie?'; '+headers.Cookie:'');const r=await fetch(base+route,{...options,headers,signal:AbortSignal.timeout(20000)});const body=await r.text();let data;try{data=JSON.parse(body);}catch{data=body;}return {status:r.status,data,headers:Object.fromEntries(r.headers)};};
  const fixture=await php(`$f=array('company'=>'Synthetic Company','location'=>'Local only','employment_type'=>'Full time','summary'=>'Synthetic CV test','responsibilities'=>'Test','qualifications'=>'Test','application_email'=>'careers@example.test','contact_email'=>'hr@example.test','closing_date'=>'2099-12-31','online_applications'=>'1'); $id=wp_insert_post(array('post_title'=>'Synthetic online applications','post_type'=>'post','post_status'=>'publish'));update_post_meta($id,'_ecs_managed','1');update_post_meta($id,'_ecs_fields',$f);echo wp_json_encode(array('id'=>$id));`);
  fs.writeFileSync(path.join(root,'cv-fixture.json'),JSON.stringify(fixture,null,2));
  if(command==='failures'){const {runFailures}=await import('./cv-failure-tests.mjs?'+Date.now());return runFailures({cli,root,php,req,fixture,credentials,httpFetch});}
  // Route-specific tests follow the finalized backend contract.
  const settings=await req('/application-settings?job_id='+fixture.id);
  check('public application settings available',settings.status,200);
  fs.writeFileSync(path.join(root,'cv-settings-response.json'),JSON.stringify(settings,null,2));
  const anonymousHr=await req('/applications');check('anonymous HR list denied',[401,403].includes(anonymousHr.status),true);
  const hr=await req('/applications',{},true);check('admin HR list authorized',hr.status,200);const clickBaseline=Number(hr.data.stats?.contact_clicks||0);
  const pdf='%PDF-1.4\n% Synthetic local CV fixture\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n';
  const resetRate=()=>php(`global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ecs_cv_%' OR option_name LIKE '_transient_timeout_ecs_cv_%'");echo 'true';`);
  const upload=async(overrides={},file=pdf,filename='synthetic.pdf',type='application/pdf')=>{
    await resetRate();const form=new FormData();
    const data={job_id:String(fixture.id),name:'Synthetic Applicant',email:'synthetic@example.test',phone:'0000000000',consent:'1',website:'',idempotency_key:'synthetic_'+crypto.randomUUID().replaceAll('-',''),token:settings.data.token,...overrides};
    for(const [k,v]of Object.entries(data))form.set(k,v);form.set('cv',new Blob([file],{type}),filename);
    return req('/apply',{method:'POST',body:form});
  };
  check('settings enabled in contained test',settings.data.enabled,true);
  check('settings retention twelve months',settings.data.retentionMonths,12);
  check('no consent denied',(await upload({consent:''})).status,400);
  check('wrong extension denied',(await upload({},pdf,'synthetic.txt','text/plain')).status,400);
  check('wrong signature denied',(await upload({},'plain text','synthetic.pdf')).status,400);
  check('oversized file denied',(await upload({},new Uint8Array(5*1024*1024+1),'synthetic.pdf')).status,400);
  check('unscanned document denied',(await upload({},pdf+'different fixture')).status,400);
  check('invalid application token denied',(await upload({token:'invalid'})).status,403);
  const variants=await php(`$ids=array();foreach(array('closed','unmanaged','disabled') as $kind){$id=wp_insert_post(array('post_title'=>'Synthetic '.$kind,'post_status'=>'publish'));$f=get_post_meta(${fixture.id},'_ecs_fields',true);if($kind!=='unmanaged')update_post_meta($id,'_ecs_managed','1');if($kind==='closed')$f['closing_date']='2000-01-01';if($kind==='disabled')$f['online_applications']='';update_post_meta($id,'_ecs_fields',$f);$ids[$kind]=$id;}echo wp_json_encode($ids);`);
  check('closed job denied',(await upload({job_id:String(variants.closed)})).status,410);
  check('unmanaged job denied',(await upload({job_id:String(variants.unmanaged)})).status,404);
  check('disabled job denied',(await upload({job_id:String(variants.disabled)})).status,403);
  const key='synthetic_duplicate_000001';const accepted=await upload({idempotency_key:key});
  check('actual multipart upload accepted',accepted.status,201);check('accepted response',accepted.data.accepted,true);
  check('duplicate upload accepted without duplicate',(await upload({idempotency_key:key})).status,200);
  const stored=await php(`global $wpdb;list($t)=ecs_cv_tables();$rows=$wpdb->get_results("SELECT * FROM $t",ARRAY_A);echo wp_json_encode(array('count'=>count($rows),'row'=>$rows[0]??null,'files'=>array_values(array_diff(scandir('/private-cvs'),array('.','..')))));`);
  check('one durable application row',stored.count,1);check('one private file',stored.files.length,1);
  if(stored.row){
    const row=stored.row;const id=Number(row.id);
    const expires=await php(`$r=ecs_cv_row(${id});echo wp_json_encode(array('expected'=>(new DateTimeImmutable($r['created_at'],new DateTimeZone('UTC')))->modify('+12 months')->format('Y-m-d H:i:s'),'actual'=>$r['expires_at'],'mode'=>substr(sprintf('%o',fileperms('/private-cvs/'.$r['file_name'])),-4)));`);
    check('expiry exactly twelve calendar months',expires.actual,expires.expected);check('private file mode',expires.mode,'0600');
    const list=await req('/applications?search=Synthetic',{},true);check('HR search returns application',Number(list.data.total),1);check('HR list hides storage filename',Object.hasOwn(list.data.applications[0],'file_name'),false);
    const downloadUrl=list.data.applications[0].download_url;
    const download=await httpFetch(downloadUrl,{headers:{Cookie:credentials.cookie},signal:AbortSignal.timeout(20000)});check('HR download authorized',download.status,200);check('HR download exact synthetic file',await download.text(),pdf);
    const guessed=await httpFetch('http://127.0.0.1:9441/private-cvs/'+row.file_name,{signal:AbortSignal.timeout(20000)});check('guessed private file URL denied',guessed.status,404);
    const status=await req('/applications/'+id+'/status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({status:'shortlisted'})},true);check('HR status update',status.data.status,'shortlisted');
    const filtered=await req('/applications?status=shortlisted',{},true);check('HR status filter',Number(filtered.data.total),1);
    const authorCredentials=await php(`$u=get_user_by('login','ecs_author');wp_set_current_user($u->ID);$exp=time()+3600;$token=WP_Session_Tokens::get_instance($u->ID)->create($exp);$_COOKIE[LOGGED_IN_COOKIE]=wp_generate_auth_cookie($u->ID,$exp,'logged_in',$token);echo wp_json_encode(array('cookie'=>LOGGED_IN_COOKIE.'='.$_COOKIE[LOGGED_IN_COOKIE],'nonce'=>wp_create_nonce('wp_rest')));`);
    const authorHr=await req('/applications',{headers:{Cookie:authorCredentials.cookie,'X-WP-Nonce':authorCredentials.nonce}});check('author HR list denied',authorHr.status,403);
    const authorDownload=await httpFetch(downloadUrl,{headers:{Cookie:authorCredentials.cookie},signal:AbortSignal.timeout(20000)});check('author download denied',authorDownload.status,403);
    const hrOnly=await php(`$u=get_user_by('login','ecs_subscriber');$u->add_cap('manage_career_applications');wp_set_current_user($u->ID);$exp=time()+3600;$token=WP_Session_Tokens::get_instance($u->ID)->create($exp);$_COOKIE[LOGGED_IN_COOKIE]=wp_generate_auth_cookie($u->ID,$exp,'logged_in',$token);echo wp_json_encode(array('cookie'=>LOGGED_IN_COOKIE.'='.$_COOKIE[LOGGED_IN_COOKIE],'nonce'=>wp_create_nonce('wp_rest')));`);
    check('HR-only subscriber list authorized',(await req('/applications',{headers:{Cookie:hrOnly.cookie,'X-WP-Nonce':hrOnly.nonce}})).status,200);
    check('HR-only subscriber job editor denied',(await req('/jobs',{headers:{Cookie:hrOnly.cookie,'X-WP-Nonce':hrOnly.nonce}})).status,403);
    await php(`global $wpdb;list($t)=ecs_cv_tables();$wpdb->update($t,array('expires_at'=>'2000-01-01 00:00:00'),array('id'=>${id}));echo 'true';`);
    const expiredDownload=await httpFetch(downloadUrl,{headers:{Cookie:credentials.cookie},signal:AbortSignal.timeout(20000)});check('expired download denied',expiredDownload.status,410);
    const purge=await req('/applications/purge',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({confirm:'DELETE EXPIRED APPLICATIONS'})},true);check('manual expired purge count',purge.data.deleted,1);
    const purged=await php(`global $wpdb;list($t)=ecs_cv_tables();echo wp_json_encode(array('row'=>ecs_cv_row(${id}),'files'=>count(array_diff(scandir('/private-cvs'),array('.','..')))));`);check('purge deletes private metadata',purged.row,null);check('purge deletes private file',purged.files,0);
    await upload();const second=await req('/applications',{},true);const secondid=Number(second.data.applications[0]?.id);
    const deletion=await req('/applications/'+secondid,{method:'DELETE'},true);check('manual deletion succeeds',deletion.data.deleted,true);
    const removed=await php(`echo wp_json_encode(array('row'=>ecs_cv_row(${secondid}),'files'=>count(array_diff(scandir('/private-cvs'),array('.','..')))));`);check('manual deletion removes metadata',removed.row,null);check('manual deletion removes file',removed.files,0);
    const click=await req('/jobs/'+fixture.id+'/contact-click',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:settings.data.token})});check('public contact click recorded',click.data.recorded,true);
    const stats=await req('/applications',{},true);check('contact click count separate from applications',Number(stats.data.stats.contact_clicks),clickBaseline+1);check('contact clicks not claimed as submissions',Number(stats.data.total),0);
  }
  const result={checks,total:checks.length,failed:checks.filter(c=>!c.pass).length};
  fs.writeFileSync(path.join(root,'cv-results.json'),JSON.stringify(result,null,2));
  return result;
}
