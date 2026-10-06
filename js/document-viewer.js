(() => {
  const dialog = document.querySelector('#documentViewer');
  if (!dialog) return;
  const stage=dialog.querySelector('.viewer-stage'), canvas=dialog.querySelector('.viewer-canvas'), image=dialog.querySelector('img');
  const pdfCanvas=dialog.querySelector('.pdf-canvas'), pdfStatus=dialog.querySelector('.pdf-status');
  const title=dialog.querySelector('#viewerTitle'), scaleLabel=dialog.querySelector('.viewer-scale'), pageLabel=dialog.querySelector('.pdf-page-label');
  const vendor=new URL('vendor/pdfjs/',document.currentScript.src).href;
  let zoom=1,rotation=0,lastFocus=null,isPdf=false,pdfDoc=null,pageNumber=1,renderTask=null,loadingTask=null,generation=0,drawSequence=0;
  function drawImage(){
    if (!image.naturalWidth || image.hidden) return;
    const swapped=rotation%180!==0,nw=image.naturalWidth,nh=image.naturalHeight;
    const fit=Math.min(Math.max(100,stage.clientWidth-32)/(swapped?nh:nw),Math.max(100,stage.clientHeight-32)/(swapped?nw:nh),1);
    const w=nw*fit*zoom,h=nh*fit*zoom;
    canvas.style.width=Math.max(stage.clientWidth,swapped?h:w)+'px';canvas.style.height=Math.max(stage.clientHeight,swapped?w:h)+'px';
    image.style.width=w+'px';image.style.height=h+'px';image.style.transform=`translate(-50%,-50%) rotate(${rotation}deg)`;
  }
  async function drawPdf(){
    if (!pdfDoc) return;
    const sequence=++drawSequence, active=generation;
    if(renderTask){renderTask.cancel();try{await renderTask.promise;}catch{}renderTask=null;}
    try{
      const page=await pdfDoc.getPage(pageNumber);
      if(sequence!==drawSequence || active!==generation)return;
      const normal=page.getViewport({scale:1,rotation});
      const fit=Math.min(Math.max(120,stage.clientWidth-32)/normal.width,Math.max(120,stage.clientHeight-32)/normal.height);
      const view=page.getViewport({scale:Math.min(4,fit*zoom),rotation});
      const ratio=Math.min(window.devicePixelRatio||1,2);
      pdfCanvas.width=Math.floor(view.width*ratio);pdfCanvas.height=Math.floor(view.height*ratio);
      pdfCanvas.style.width=view.width+'px';pdfCanvas.style.height=view.height+'px';
      renderTask=page.render({canvasContext:pdfCanvas.getContext('2d'),viewport:view,transform:ratio===1?null:[ratio,0,0,ratio,0,0]});
      await renderTask.promise;
      if(sequence!==drawSequence || active!==generation)return;
      pdfStatus.hidden=true;pageLabel.textContent=`หน้า ${pageNumber} / ${pdfDoc.numPages}`;
      dialog.querySelector('[data-viewer-action=previous]').disabled=pageNumber<=1;
      dialog.querySelector('[data-viewer-action=next]').disabled=pageNumber>=pdfDoc.numPages;
    }catch(error){if(error.name!=='RenderingCancelledException' && active===generation){pdfStatus.textContent='แสดง PDF ไม่สำเร็จ กรุณาเปิดแท็บใหม่หรือดาวน์โหลด';pdfStatus.hidden=false;}}
  }
  function draw(){scaleLabel.textContent=Math.round(zoom*100)+'%';if(isPdf)drawPdf();else drawImage();}
  function release(){generation++;drawSequence++;renderTask?.cancel();renderTask=null;loadingTask?.destroy();loadingTask=null;pdfDoc=null;}
  function close(){dialog.close();release();image.removeAttribute('src');lastFocus?.focus();}
  document.querySelectorAll('[data-document-url]').forEach(button=>button.addEventListener('click',async()=>{
    release();lastFocus=button;zoom=1;rotation=0;pageNumber=1;title.textContent=button.dataset.label;
    isPdf=button.dataset.type==='pdf';image.hidden=isPdf;canvas.hidden=isPdf;pdfCanvas.hidden=!isPdf;pdfStatus.hidden=!isPdf;
    dialog.querySelectorAll('[data-pdf-tool]').forEach(el=>el.hidden=!isPdf);
    dialog.querySelector('.viewer-download').href=button.dataset.documentUrl+'&download=1';
    dialog.querySelector('.viewer-original').href=button.dataset.documentUrl;
    dialog.showModal();stage.scrollTop=0;stage.scrollLeft=0;
    if(!isPdf){image.src=button.dataset.documentUrl;draw();return;}
    pdfStatus.textContent='กำลังโหลด PDF…';pageLabel.textContent='';const active=generation;
    try{
      const pdfjs=await import(vendor+'pdf.mjs');
      if(active!==generation)return;
      pdfjs.GlobalWorkerOptions.workerSrc=vendor+'pdf.worker.mjs';
      loadingTask=pdfjs.getDocument({url:button.dataset.documentUrl,cMapUrl:vendor+'cmaps/',cMapPacked:true,standardFontDataUrl:vendor+'standard_fonts/',isEvalSupported:false});
      pdfDoc=await loadingTask.promise;
      if(active===generation)draw();
    }catch(error){if(active===generation){pdfStatus.textContent='เปิด PDF ไม่สำเร็จ กรุณาเปิดแท็บใหม่หรือดาวน์โหลด';pdfStatus.hidden=false;}}
  }));
  image.addEventListener('load',drawImage);image.addEventListener('error',()=>{scaleLabel.textContent='ไม่พบไฟล์ภาพ';});
  dialog.querySelectorAll('[data-viewer-action]').forEach(button=>button.addEventListener('click',()=>{
    switch(button.dataset.viewerAction){case 'close':close();return;case 'in':zoom=Math.min(6,zoom+.25);break;case 'out':zoom=Math.max(.25,zoom-.25);break;case 'rotate':rotation=(rotation+90)%360;break;case 'fit':zoom=1;rotation=0;break;case 'previous':if(pdfDoc)pageNumber=Math.max(1,pageNumber-1);break;case 'next':if(pdfDoc)pageNumber=Math.min(pdfDoc.numPages,pageNumber+1);break;}draw();
  }));
  dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
  dialog.addEventListener('keydown',event=>{if(['+','-','r','0'].includes(event.key)){event.preventDefault();dialog.querySelector(`[data-viewer-action="${{'+':'in','-':'out','r':'rotate','0':'fit'}[event.key]}"]`).click();}});
  new ResizeObserver(draw).observe(stage);
})();
