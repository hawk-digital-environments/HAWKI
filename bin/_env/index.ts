import { Application } from '@/Application.ts';
import * as process from 'node:process';


await (new Application()).run(process.argv);
