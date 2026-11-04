(() => {
 'use strict';
 // Only a single-cell box / isolated downward arrow / single-cell box is a sequence.
 // Prose, comparison tables and branching diagrams retain their original structure.
 const cell=table=>table?.tagName==='TABLE'&&table.rows.length===1&&table.rows[0].cells.length===1&&table.rows[0].cells[0].colSpan===1&&table.rows[0].cells[0].rowSpan===1&&!table.querySelector('table')?table.rows[0].cells[0]:null;
 const arrow=node=>node?.tagName==='P'&&/^[↓⇓⬇]\uFE0F?$/.test(node.textContent.trim())&&!node.querySelector('a,img,svg,table');
 window.OIReaderDiagrams={enhance(content){
  let count=0;
  for(const first of [...content.children]){
   if(first.parentElement!==content||!cell(first))continue;
   const boxes=[first],arrows=[];let next=first.nextElementSibling;
   while(arrow(next)&&cell(next.nextElementSibling)){arrows.push(next);boxes.push(next.nextElementSibling);next=next.nextElementSibling.nextElementSibling;}
   if(boxes.length<2)continue;
   const list=document.createElement('ol');list.className='oi-flow';list.setAttribute('role','list');list.setAttribute('aria-label','Étapes du schéma');first.before(list);
   boxes.forEach((box,index)=>{
    const item=document.createElement('li');item.className='oi-flow-step';
    const card=document.createElement('div');card.className='oi-flow-card';
    const number=document.createElement('span');number.className='oi-flow-number';number.setAttribute('aria-hidden','true');number.textContent=String(index+1).padStart(2,'0');
    const copy=document.createElement('div');copy.className='oi-flow-copy';copy.append(...cell(box).childNodes);
    card.append(number,copy);item.append(card);list.append(item);box.remove();
   });
   arrows.forEach(node=>node.remove());count++;
  }
  return count;
 }};
})();
