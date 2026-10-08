const fs=require('node:fs');const path=require('node:path');const {expect}=require('@playwright/test');
async function confirmEmail(page,email){
 let mail;
 await expect.poll(()=>{
  for(const filename of fs.readdirSync('.runtime/mail')){
   try{const item=JSON.parse(fs.readFileSync(path.join('.runtime/mail',filename),'utf8'));const recipients=Array.isArray(item.to)?item.to:[item.to];if(recipients.includes(email)&&item.subject.includes('Confirme ton adresse')){mail=item;return true;}}catch{}
  }return false;
 },{timeout:15000,message:'E-mail de vérification capturé localement'}).toBeTruthy();
 const url=new URL(mail.message.match(/https?:\/\/[^\s<>]+/)[0]);expect(url.origin).toBe('http://127.0.0.1:8080');
 const nonce=await page.evaluate(()=>OI.nonce);
 await page.goto(url.href);
 const state=await page.request.get('/?rest_route=/oi/v1/account',{headers:{'X-WP-Nonce':nonce}});
 expect((await state.json()).verified).toBe(false);
 await page.getByRole('button',{name:'Confirmer mon adresse',exact:true}).click();await expect(page).toHaveURL(/\/espace-revision\/$/);
 return url.href;
}
module.exports={confirmEmail};
