// Mock jQuery and WordPress globals
global.$ = jest.fn(() => ({
  hide: jest.fn(),
  show: jest.fn(),
  html: jest.fn(),
  val: jest.fn(),
  on: jest.fn(),
  each: jest.fn()
}));

global.$.ajax = jest.fn();

global.quizAjax = {
  ajaxurl: '/wp-admin/admin-ajax.php',
  nonce: 'test-nonce'
};

global.console.log = jest.fn();