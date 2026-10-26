import fs from 'node:fs';
import path from 'node:path';
export async function runFailures({cli,root,php,req,fixture,credentials,httpFetch}){
 const checks=[];const check=(name,actual,expected)=>{checks.push({name,actual,expected,pass:actual===expected});fs.writeFileSync(path.join(root,'cv-failure-results.json'),JSON.stringify({checks,total:checks.length,failed:checks.filter(c=>!c.pass).length},null,2));};
 const mu=`<?php
add_filter('query',function($q){
 if(!preg_match('/^(INSERT|UPDATE)/i',ltrim($q))||strpos($q,'ecs_')===false)return $q;
 $mode=get_option('ecs_synthetic_failure','');
 $hit=($mode==='insert'&&stripos($q,'INSERT')===0&&strpos($q,'ecs_applications')!==false)||($mode==='finalize'&&stripos($q,'UPDATE')===0&&strpos($q,'ecs_applications')!==false&&strpos($q,"'new'")!==false)||($mode==='audit'&&stripos($q,'INSERT')===0&&strpos($q,'ecs_cv_events')!==false&&strpos($q,'contact_click')!==false);
 if($hit){delete_option('ecs_synthetic_failure');return 'INSERT INTO ecs_synthetic_missing_table (id) VALUES (1)';}
 if($mode==='move'&&stripos($q,'INSERT')===0&&strpos($q,'ecs_applications')!==false){delete_option('ecs_synthetic_failure');rename('/private-cvs','/private-cvs-away');}
 return $q;
});`;
 await cli.playground.writeFile('/wordpress/wp-content/mu-plugins/ecs-synthetic-failure.php',mu);
 const settings=await req('/application-settings');const pdf='%PDF-1.4\n% Synthetic local CV fixture\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n';
 const set=async(mode)=>php(`update_option('ecs_synthetic_failure','${mode}');global $wpdb;$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ecs_cv_%' OR option_name LIKE '_transient_timeout_ecs_cv_%'");echo 'true';`);
 const upload=async(key='synthetic_failure_'+crypto.randomUUID().replaceAll('-',''))=>{const f=new FormData();for(const [k,v]of Object.entries({job_id:String(fixture.id),name:'Synthetic Failure Test',email:'failure@example.test',consent:'1',website:'',idempotency_key:key,token:settings.data.token}))f.set(k,v);f.set('cv',new Blob([pdf],{type:'application/pdf'}),'synthetic.pdf');return req('/apply',{method:'POST',body:f});};
 const state=()=>php(`global $wpdb;list($t)=ecs_cv_tables();echo wp_json_encode(array('rows'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $t"),'files'=>count(array_diff(scandir('/private-cvs'),array('.','..')))));`);
 try{
  for(const mode of ['insert','finalize','move']){
   await set(mode);const r=await upload();check(mode+' failure returns 500',r.status,500);
   if(mode==='move')await php(`if(is_dir('/private-cvs-away'))rename('/private-cvs-away','/private-cvs');echo 'true';`);
   const s=await state();check(mode+' failure leaves no orphan file',s.files,0);check(mode+' failure cleans pending metadata',s.rows,0);
  }
  const before=await req('/applications',{},true);await set('audit');const click=await req('/jobs/'+fixture.id+'/contact-click',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:settings.data.token})});check('failed contact audit returns 500',click.status,500);check('failed contact audit never claims recorded',click.data.recorded===true,false);const after=await req('/applications',{},true);check('failed contact audit leaves count unchanged',after.data.stats.contact_clicks,before.data.stats.contact_clicks);
  await set('');const key='synthetic_pending_obstruction_0001';check('cleanup fixture actual upload succeeds',(await upload(key)).status,201);
  const retained=await php(`global $wpdb;list($t)=ecs_cv_tables();$r=$wpdb->get_row("SELECT * FROM $t LIMIT 1",ARRAY_A);$id=(int)$r['id'];$p='/private-cvs/'.$r['file_name'];unlink($p);mkdir($p);$wpdb->update($t,array('status'=>'upload_pending'),array('id'=>$id));ecs_cv_abort_pending($id,$p);echo wp_json_encode(array('id'=>$id,'retained'=>(bool)ecs_cv_row($id),'obstruction'=>is_dir($p),'filename'=>$r['file_name']));`);
  check('failed unlink preserves tracked pending row',retained.retained,true);check('failed unlink simulated by directory obstruction',retained.obstruction,true);
  const list=await req('/applications',{},true);const pending=list.data.applications.find(a=>Number(a.id)===retained.id);check('pending row exposes no download',pending.download_url,null);
  const update=await req('/applications/'+retained.id+'/status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({status:'new'})},true);check('pending row cannot be marked completed',update.status,409);
  await set('');check('duplicate pending upload never accepted',(await upload(key)).status,503);
  const expired=await php(`global $wpdb;list($t)=ecs_cv_tables();$wpdb->update($t,array('status'=>'new','expires_at'=>'2000-01-01 00:00:00'),array('id'=>${retained.id}));echo 'true';`);
  const expiredList=await req('/applications',{},true);check('new counter excludes expired rows',Number(expiredList.data.stats.new),0);
  await php(`$r=ecs_cv_row(${retained.id});rmdir('/private-cvs/'.$r['file_name']);file_put_contents('/private-cvs/'.$r['file_name'],'synthetic cleanup');echo 'true';`);
  const removed=await req('/applications/'+retained.id,{method:'DELETE'},true);check('HR can delete tracked failed upload',removed.data.deleted,true);const end=await state();check('failure fixtures leave zero application rows',end.rows,0);check('failure fixtures leave zero private files',end.files,0);
 }finally{await php(`delete_option('ecs_synthetic_failure');if(is_dir('/private-cvs-away'))rename('/private-cvs-away','/private-cvs');echo 'true';`);await cli.playground.unlink('/wordpress/wp-content/mu-plugins/ecs-synthetic-failure.php');}
 const result={checks,total:checks.length,failed:checks.filter(c=>!c.pass).length};fs.writeFileSync(path.join(root,'cv-failure-results.json'),JSON.stringify(result,null,2));return result;
}
