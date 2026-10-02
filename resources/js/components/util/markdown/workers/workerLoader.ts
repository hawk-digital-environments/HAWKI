const loadedWorkers = new Map<string, Worker>();

export function loadWorker(url:string){
    if(loadedWorkers.has(url)){
        return loadedWorkers.get(url)!;
    }
    const blob = new Blob(
        [`import ${JSON.stringify(new URL(url, import.meta.url))}`],
        {type: 'application/javascript'}
    );
    const objURL = URL.createObjectURL(blob);
    const worker = new Worker(objURL, {type: 'module'});
    worker.addEventListener('error', () => URL.revokeObjectURL(objURL));
    loadedWorkers.set(url, worker);
    return worker;
}
