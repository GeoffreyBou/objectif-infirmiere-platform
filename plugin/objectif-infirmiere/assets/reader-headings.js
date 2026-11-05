/* Numbering is presentation only: the imported Word content is never rewritten. */
(() => {
  'use strict';
  // A quantity is part of the title, even when it happens to equal the section index.
  const quantity=/^(?:%|°|ans?\b|années?\b|mois\b|semaines?\b|jours?\b|heures?\b|minutes?\b|secondes?\b|étapes?\b|phases?\b|types?\b|patients?\b|personnes?\b|mg\b|g\b|kg\b|ml\b|cl\b|l\b|mmhg\b|mmol\b|cm\b|mm\b|à\s*\d|sur\s*\d)/iu;
  window.OIReaderHeadings={enhance(headings){
    let section=0,subsection=0;
    const entries=Array.from(headings,(heading,i)=>{
      if(heading.tagName==='H2'){section++;subsection=0;}else subsection++;
      const expected=heading.tagName==='H2'?String(section):`${section||1}.${subsection}`;
      const raw=heading.textContent.trim();
      const explicit=raw.match(/^(\d+(?:\.\d+)*)(?:[.)]\s+|\s*[–—-]\s+)/);
      const bare=explicit?null:raw.match(/^(\d+(?:\.\d+)*)\s+(?=\S)/);
      const candidate=bare&&(bare[1]===expected||(heading.tagName==='H3'&&bare[1]===String(subsection)))&&!quantity.test(raw.slice(bare[0].length))?bare:null;
      return {heading,i,raw,expected,explicit,candidate,group:heading.tagName==='H2'?'main':'sub:'+section};
    });
    // Bare numbers are removed only when neighbouring headings confirm a sequence.
    // Grouping by heading level avoids breaking a section sequence with subsections.
    const groups=new Map();for(const entry of entries){if(!groups.has(entry.group))groups.set(entry.group,[]);groups.get(entry.group).push(entry);}
    const consecutive=(a,b)=>{
      const pa=a?.explicit||a?.candidate,pb=b?.explicit||b?.candidate;if(!pa||!pb)return false;
      const left=pa[1].split('.'),right=pb[1].split('.');return left.length===right.length&&left.slice(0,-1).join('.')===right.slice(0,-1).join('.')&&Number(right.at(-1))===Number(left.at(-1))+1;
    };
    for(const group of groups.values())group.forEach((entry,i)=>{entry.prefix=entry.explicit||(entry.candidate&&(consecutive(group[i-1],entry)||consecutive(entry,group[i+1]))?entry.candidate:null);});
    return entries.map(({heading,i,raw,expected,prefix})=>{
      const existing=heading.querySelector(':scope > .oi-section-number'),existingTitle=heading.querySelector(':scope > .oi-section-title-text');
      if(existing&&existingTitle)return {id:heading.id,title:existingTitle.textContent.trim(),number:existing.textContent,sub:heading.tagName==='H3'};
      heading.id=`oi-section-${i}`;
      const number=prefix?prefix[1]:expected,title=prefix?raw.slice(prefix[0].length):raw;
      // Prefixes can span several Word runs. Keep links, emphasis and their text intact.
      if(prefix){const walker=document.createTreeWalker(heading,NodeFilter.SHOW_TEXT);let remaining=heading.textContent.length-heading.textContent.trimStart().length+prefix[0].length,node;while(remaining&&(node=walker.nextNode())){const removed=Math.min(remaining,node.textContent.length);node.textContent=node.textContent.slice(removed);remaining-=removed;}}
      const titleNode=document.createElement('span');titleNode.className='oi-section-title-text';titleNode.append(...heading.childNodes);
      const badge=document.createElement('span');badge.className='oi-section-number';badge.textContent=number.padStart(2,'0');
      heading.classList.add('oi-numbered-heading');heading.append(badge,titleNode);
      return {id:heading.id,title,number:badge.textContent,sub:heading.tagName==='H3'};
    });
  }};
})();
